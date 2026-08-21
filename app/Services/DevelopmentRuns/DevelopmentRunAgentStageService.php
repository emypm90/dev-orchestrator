<?php

namespace App\Services\DevelopmentRuns;

use App\Models\DevelopmentRun;
use Illuminate\Support\Facades\DB;
use Throwable;

class DevelopmentRunAgentStageService
{
    public function executePlan(DevelopmentRun $run, OpenCodeExecutionRunner $runner, StageAgentContract $contract): void
    {
        if ($run->fresh()->status !== 'plan_running') {
            return;
        }

        $agents = $runner->stageAgents();
        $brief = $run->artifacts()->where('type', 'technical_brief')->exists()
            ? null
            : $this->technicalBriefResultFor($run, $runner);

        if ($run->fresh()->status !== 'plan_running') {
            return;
        }

        DB::transaction(function () use ($run, $contract, $agents, $brief) {
            $run->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Plan'],
                [
                    'body' => $contract->render('Plan', $agents['planning'], 'Convertir contexto inicial en brief técnico accionable.', ['Contexto inicial'], ['Ejecutar OpenCode con agente Plan si está disponible', 'Detectar restricciones', 'Proponer criterios de aceptación', 'Usar fallback determinístico si el agente no responde'], ['technical_brief', 'status: completed | fallback | failed', 'risks']),
                    'metadata' => ['stage' => 'plan', 'agent' => $agents['planning']],
                    'created_by' => 'system',
                ],
            );

            $run->artifacts()->firstOrCreate(
                ['type' => 'technical_brief'],
                [
                    'title' => 'Brief técnico inicial',
                    'body' => $brief['body'] ?? $this->technicalBriefFor($run),
                    'metadata' => $brief['metadata'] ?? ['generator' => 'deterministic', 'version' => 1, 'fallback' => true],
                    'created_by' => $brief['created_by'] ?? 'system',
                ],
            );

            $this->markBackgroundArtifactFinished($run, 'plan_background_run', 'completed');
            $run->update(['active_stage' => 'plan', 'status' => 'planning']);
        });
    }

    public function executeSlices(DevelopmentRun $run, OpenCodeExecutionRunner $runner, StageAgentContract $contract): void
    {
        if ($run->fresh()->status !== 'slices_running') {
            return;
        }

        $technicalBrief = $run->artifacts()->where('type', 'technical_brief')->first();
        if (! $technicalBrief) {
            $this->markStageBlocked($run, 'slices_background_run', 'slices', 'No se encontró el brief técnico requerido para definir slices.');

            return;
        }

        $agents = $runner->stageAgents();
        $slices = $run->artifacts()->where('type', 'implementation_slices')->exists()
            ? null
            : $this->implementationSlicesResultFor($run, $technicalBrief, $runner);

        if ($run->fresh()->status !== 'slices_running') {
            return;
        }

        DB::transaction(function () use ($run, $technicalBrief, $contract, $agents, $slices) {
            $run->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Slices'],
                [
                    'body' => $contract->render('Slices', $agents['slicing'], 'Dividir el brief en slices chicos, verificables y revisables.', ['Brief técnico inicial'], ['Ejecutar OpenCode con agente Slices si está disponible', 'Leer brief', 'Definir slices ordenados', 'Mantener cada slice por debajo de carga saludable de revisión', 'Usar fallback determinístico si el agente no responde'], ['implementation_slices', 'review workload forecast', 'next recommended slice', 'status: completed | fallback | failed']),
                    'metadata' => ['stage' => 'slices', 'agent' => $agents['slicing']],
                    'created_by' => 'system',
                ],
            );

            $run->artifacts()->firstOrCreate(
                ['type' => 'implementation_slices'],
                [
                    'title' => 'Slices de implementación',
                    'body' => $slices['body'] ?? $this->implementationSlicesFor($run, $technicalBrief->title),
                    'metadata' => $slices['metadata'] ?? ['generator' => 'deterministic', 'version' => 1, 'fallback' => true],
                    'created_by' => $slices['created_by'] ?? 'system',
                ],
            );

            $this->markBackgroundArtifactFinished($run, 'slices_background_run', 'completed');
            $run->update(['active_stage' => 'slices', 'status' => 'slicing']);
        });
    }

    public function executeReview(DevelopmentRun $run, OpenCodeExecutionRunner $runner, StageAgentContract $contract): void
    {
        if ($run->fresh()->status !== 'review_running') {
            return;
        }

        $qaReport = $run->artifacts()->where('type', 'qa_report')->first();
        if (! $qaReport) {
            $this->markStageBlocked($run, 'review_background_run', 'review', 'No se encontró el reporte QA requerido para cerrar el run.');

            return;
        }

        $agents = $runner->stageAgents();
        $review = $run->artifacts()->where('type', 'review_report')->exists()
            ? null
            : $this->reviewReportResultFor($run->fresh(['artifacts']), $qaReport, $runner);

        if ($run->fresh()->status !== 'review_running') {
            return;
        }

        DB::transaction(function () use ($run, $contract, $agents, $qaReport, $review) {
            $run->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Revisión'],
                [
                    'body' => $contract->render('Revisión', $agents['review'], 'Cerrar el Development Run con resumen, evidencia y próximo paso humano.', ['context', 'technical_brief', 'implementation_slices', 'build_plan', 'opencode_execution', 'qa_report'], ['Ejecutar OpenCode con agente Review si está disponible', 'Sintetizar artifacts', 'No ejecutar cambios', 'Marcar cierre local', 'Usar fallback determinístico si el agente no responde'], ['review_report', 'final_status', 'human handoff', 'status: completed | fallback | failed']),
                    'metadata' => ['stage' => 'review', 'agent' => $agents['review']],
                    'created_by' => 'system',
                ],
            );

            $run->artifacts()->firstOrCreate(
                ['type' => 'review_report'],
                [
                    'title' => 'Cierre del Development Run',
                    'body' => $review['body'] ?? $this->reviewReportBody($run->fresh(['artifacts']), $qaReport),
                    'metadata' => $review['metadata'] ?? ['agent' => $agents['review'], 'status' => 'completed', 'generator' => 'deterministic', 'fallback' => true],
                    'created_by' => $review['created_by'] ?? 'review-agent',
                ],
            );

            $this->markBackgroundArtifactFinished($run, 'review_background_run', 'completed');
            $run->update(['active_stage' => 'review', 'status' => 'completed', 'completed_at' => now()]);
        });
    }

    private function markStageBlocked(DevelopmentRun $run, string $artifactType, string $stage, string $message): void
    {
        DB::transaction(function () use ($run, $artifactType, $stage, $message) {
            $run->artifacts()->updateOrCreate(
                ['type' => $artifactType],
                [
                    'title' => ucfirst($stage).' bloqueado',
                    'body' => $message,
                    'metadata' => ['stage' => $stage, 'status' => 'blocked', 'finished_at' => now()->toISOString()],
                    'created_by' => 'system',
                ],
            );

            $run->update(['active_stage' => $stage, 'status' => "{$stage}_blocked"]);
        });
    }

    private function markBackgroundArtifactFinished(DevelopmentRun $run, string $artifactType, string $status): void
    {
        $artifact = $run->artifacts()->where('type', $artifactType)->first();
        if (! $artifact) {
            return;
        }

        $stage = match ($artifactType) {
            'plan_background_run' => 'Plan',
            'slices_background_run' => 'Slices',
            'review_background_run' => 'Revisión',
            default => 'Etapa',
        };

        $normalizedStatus = in_array($status, ['completed', 'passed'], true) ? 'completed' : $status;
        $titleStatus = match ($normalizedStatus) {
            'completed' => 'completado',
            'failed' => 'fallido',
            'blocked' => 'bloqueado',
            default => $normalizedStatus,
        };

        $artifact->update([
            'title' => "{$stage} {$titleStatus}",
            'metadata' => [...($artifact->metadata ?? []), 'status' => $normalizedStatus, 'finished_at' => now()->toISOString()],
        ]);
    }

    /**
     * @return array{body: string, metadata: array<string, mixed>, created_by: string}
     */
    private function technicalBriefResultFor(DevelopmentRun $run, OpenCodeExecutionRunner $runner): array
    {
        $profile = $runner->planningProfile();
        $fallback = fn (string $reason, ?int $exitCode = null, string $output = ''): array => [
            'body' => $this->technicalBriefFor($run),
            'metadata' => [
                'generator' => 'deterministic',
                'version' => 2,
                'fallback' => true,
                'fallback_reason' => $reason,
                'exit_code' => $exitCode,
                'opencode_output' => $output !== '' ? substr($output, 0, 2000) : null,
                ...$profile,
            ],
            'created_by' => 'system',
        ];

        if (! $runner->isAvailable()) {
            return $fallback('opencode_unavailable');
        }

        $workingDirectory = $this->planWorkingDirectory($run);

        try {
            $result = $runner->runPlanning($workingDirectory, $this->planningPromptFor($run));
        } catch (Throwable $exception) {
            return $fallback('opencode_exception', 1, $exception->getMessage());
        }

        if ($result['status'] !== 'completed' || trim($result['output']) === '') {
            return $fallback('opencode_failed', $result['exit_code'], $result['output']);
        }

        return [
            'body' => trim($result['output']),
            'metadata' => [
                'generator' => 'opencode',
                'version' => 1,
                'fallback' => false,
                'status' => $result['status'],
                'exit_code' => $result['exit_code'],
                'working_directory' => $workingDirectory,
                ...$profile,
            ],
            'created_by' => 'opencode',
        ];
    }

    private function planWorkingDirectory(DevelopmentRun $run): string
    {
        $repository = trim((string) $run->repository);

        return $repository !== '' && is_dir($repository) ? $repository : base_path();
    }

    private function planningPromptFor(DevelopmentRun $run): string
    {
        return "Development Run\n"
            ."Título: {$run->title}\n"
            ."Contexto inicial:\n{$run->initial_context}\n\n"
            .'Repositorio declarado: '.($run->repository ?: 'No definido')."\n"
            .'Proyecto: '.($run->project ?: 'No definido')."\n"
            .'Prioridad: '.($run->priority ?: 'No definida')."\n\n"
            .'Generá un brief técnico accionable para avanzar a Slices. No modifiques archivos. No ejecutes Git. No ejecutes tests.';
    }

    /**
     * @return array{body: string, metadata: array<string, mixed>, created_by: string}
     */
    private function implementationSlicesResultFor(DevelopmentRun $run, $technicalBrief, OpenCodeExecutionRunner $runner): array
    {
        $profile = $runner->slicingProfile();
        $fallback = fn (string $reason, ?int $exitCode = null, string $output = ''): array => [
            'body' => $this->implementationSlicesFor($run, $technicalBrief->title),
            'metadata' => [
                'generator' => 'deterministic',
                'version' => 2,
                'fallback' => true,
                'fallback_reason' => $reason,
                'exit_code' => $exitCode,
                'opencode_output' => $output !== '' ? substr($output, 0, 2000) : null,
                ...$profile,
            ],
            'created_by' => 'system',
        ];

        if (! $runner->isAvailable()) {
            return $fallback('opencode_unavailable');
        }

        $workingDirectory = $this->planWorkingDirectory($run);

        try {
            $result = $runner->runSlicing($workingDirectory, $this->slicingPromptFor($run, $technicalBrief));
        } catch (Throwable $exception) {
            return $fallback('opencode_exception', 1, $exception->getMessage());
        }

        if ($result['status'] !== 'completed' || trim($result['output']) === '') {
            return $fallback('opencode_failed', $result['exit_code'], $result['output']);
        }

        return [
            'body' => trim($result['output']),
            'metadata' => [
                'generator' => 'opencode',
                'version' => 1,
                'fallback' => false,
                'status' => $result['status'],
                'exit_code' => $result['exit_code'],
                'working_directory' => $workingDirectory,
                'source_technical_brief_id' => $technicalBrief->id,
                ...$profile,
            ],
            'created_by' => 'opencode',
        ];
    }

    private function slicingPromptFor(DevelopmentRun $run, $technicalBrief): string
    {
        return "Development Run\n"
            ."Título: {$run->title}\n"
            .'Proyecto: '.($run->project ?: 'No definido')."\n"
            .'Prioridad: '.($run->priority ?: 'No definida')."\n\n"
            ."Brief técnico de entrada:\n{$technicalBrief->body}\n\n"
            .'Generá slices de implementación chicos, ordenados y revisables para avanzar a Build. No modifiques archivos. No ejecutes Git. No ejecutes tests.';
    }

    private function technicalBriefFor(DevelopmentRun $run): string
    {
        $context = trim(preg_replace('/\s+/', ' ', $run->initial_context));
        $restrictionLines = collect(preg_split('/\R+|(?<=[.!?])\s+/', $run->initial_context))
            ->filter(fn (string $line) => preg_match('/\b(no tocar|no|restricci[oó]n|debe|necesita)\b/i', $line))
            ->map(fn (string $line) => '- '.trim($line, " \t\n\r\0\x0B.-"))
            ->take(3)
            ->implode("\n");

        return "Objetivo\n- {$run->title}\n\n"
            ."Contexto relevante\n- {$context}\n\n"
            ."Restricciones detectadas\n"
            .($restrictionLines ?: '- No se detectaron restricciones explícitas en el contexto.')
            ."\n\nPlan inicial\n"
            ."- Confirmar alcance.\n"
            ."- Identificar archivos/áreas impactadas.\n"
            ."- Definir slice implementable.\n"
            ."- Preparar verificación manual/automática.\n\n"
            ."Criterios de aceptación iniciales\n"
            ."- El cambio resuelve el problema descripto.\n"
            ."- La solución queda cubierta por pruebas o evidencia de QA.\n"
            ."- No se ejecutan acciones de Git sin aprobación humana.\n\n"
            ."Datos del run\n"
            .'- Repositorio: '.($run->repository ?: 'No definido')."\n"
            .'- Proyecto: '.($run->project ?: 'No definido')."\n"
            .'- Prioridad: '.($run->priority ?: 'No definida');
    }

    private function implementationSlicesFor(DevelopmentRun $run, string $technicalBriefTitle): string
    {
        return "Run: {$run->title}\n"
            ."Punto de partida: {$technicalBriefTitle}\n\n"
            ."Slice 1 — Ubicar área mínima de cambio\n"
            ."Objetivo: confirmar alcance y archivos/áreas impactadas.\n"
            ."Criterios: brief revisado, riesgos visibles, sin cambios de Git automáticos.\n\n"
            ."Slice 2 — Implementar cambio principal\n"
            ."Objetivo: aplicar el cambio más chico que resuelva el problema.\n"
            ."Criterios: el cambio queda aplicado en archivos mínimos y listo para QA.\n\n"
            ."Slice 3 — QA y refinamiento\n"
            ."Objetivo: validar en entorno local y ajustar bordes detectados.\n"
            .'Criterios: pruebas pasan, evidencia lista para revisión humana.';
    }

    /**
     * @return array{body: string, metadata: array<string, mixed>, created_by: string}
     */
    private function reviewReportResultFor(DevelopmentRun $run, $qaReport, OpenCodeExecutionRunner $runner): array
    {
        $profile = $runner->reviewProfile();
        $fallback = fn (string $reason, ?int $exitCode = null, string $output = ''): array => [
            'body' => $this->reviewReportBody($run, $qaReport),
            'metadata' => [
                'generator' => 'deterministic',
                'fallback' => true,
                'fallback_reason' => $reason,
                'status' => 'completed',
                'exit_code' => $exitCode,
                'opencode_output' => $output !== '' ? substr($output, 0, 2000) : null,
                ...$profile,
            ],
            'created_by' => 'review-agent',
        ];

        if (! $runner->isAvailable()) {
            return $fallback('opencode_unavailable');
        }

        $workingDirectory = $this->planWorkingDirectory($run);

        try {
            $result = $runner->runReview($workingDirectory, $this->reviewPromptFor($run));
        } catch (Throwable $exception) {
            return $fallback('opencode_exception', 1, $exception->getMessage());
        }

        if ($result['status'] !== 'completed' || trim($result['output']) === '') {
            return $fallback('opencode_failed', $result['exit_code'], $result['output']);
        }

        return [
            'body' => trim($result['output']),
            'metadata' => [
                'generator' => 'opencode',
                'fallback' => false,
                'status' => 'completed',
                'exit_code' => $result['exit_code'],
                'working_directory' => $workingDirectory,
                ...$profile,
            ],
            'created_by' => 'opencode',
        ];
    }

    private function reviewPromptFor(DevelopmentRun $run): string
    {
        $artifacts = $run->artifacts
            ->reject(fn ($artifact) => in_array($artifact->type, ['review_report', 'review_background_run'], true))
            ->map(fn ($artifact) => "## {$artifact->title} ({$artifact->type})\n{$artifact->body}")
            ->implode("\n\n");

        return "Development Run\n"
            ."Título: {$run->title}\n"
            .'Repositorio: '.($run->repository ?: 'No definido')."\n"
            .'Proyecto: '.($run->project ?: 'No definido')."\n\n"
            ."Artifacts disponibles:\n{$artifacts}\n\n"
            .'Generá el reporte final de cierre local. No modifiques archivos. No ejecutes Git. No ejecutes tests.';
    }

    private function reviewReportBody(DevelopmentRun $run, $qaReport): string
    {
        $artifactSummary = $run->artifacts
            ->reject(fn ($artifact) => in_array($artifact->type, ['review_report', 'review_background_run'], true))
            ->map(fn ($artifact) => "- {$artifact->title} ({$artifact->type})")
            ->implode("\n");

        return "Cierre del Development Run\n"
            ."- Run: {$run->title}\n"
            ."- Estado final: completed\n"
            .'- Repositorio: '.($run->repository ?: 'No definido')."\n"
            .'- Proyecto: '.($run->project ?: 'No definido')."\n\n"
            ."Artifacts generados\n"
            .($artifactSummary ?: '- No hay artifacts previos.')."\n\n"
            ."Evidencia QA\n"
            ."- {$qaReport->title}\n\n"
            ."Handoff humano\n"
            ."- Revisar artifacts antes de integrar cambios.\n"
            ."- No se realizaron commits, stage, push ni cambios de remotos desde Command Flow.\n"
            .'- Si el resultado requiere integración, hacerlo manualmente con revisión humana.';
    }
}
