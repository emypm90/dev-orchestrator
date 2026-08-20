<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunBackgroundProcess;
use App\Services\DevelopmentRuns\DevelopmentRunStaleExecutionDetector;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\StageAgentContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevelopmentRunController extends Controller
{
    public function create()
    {
        return view('development-runs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'initial_context' => ['required', 'string'],
            'repository' => ['nullable', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
        ]);

        $run = DB::transaction(function () use ($data) {
            $run = DevelopmentRun::create([
                ...$data,
                'status' => 'intake',
                'active_stage' => 'contexto',
                'started_at' => now(),
            ]);

            $run->artifacts()->create([
                'type' => 'context',
                'title' => 'Contexto inicial',
                'body' => $run->initial_context,
                'created_by' => 'manual',
            ]);

            return $run;
        });

        return redirect()->route('development-runs.show', $run);
    }

    public function show(DevelopmentRun $developmentRun, DevelopmentRunStaleExecutionDetector $staleExecutionDetector)
    {
        $developmentRun = $staleExecutionDetector->recoverIfStale($developmentRun);

        return view('development-runs.show', ['run' => $developmentRun->load(['artifacts' => fn ($query) => $query->oldest()])]);
    }

    public function status(DevelopmentRun $developmentRun, DevelopmentRunStaleExecutionDetector $staleExecutionDetector): JsonResponse
    {
        $developmentRun = $staleExecutionDetector->recoverIfStale($developmentRun);

        $artifacts = $developmentRun->artifacts()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return response()->json([
            'id' => $developmentRun->id,
            'status' => $developmentRun->status,
            'active_stage' => $developmentRun->active_stage,
            'completed' => $developmentRun->completed_at !== null,
            'running' => in_array($developmentRun->status, ['build_running', 'qa_running'], true),
            'artifacts' => $artifacts,
            'updated_at' => optional($developmentRun->updated_at)->toISOString(),
        ]);
    }

    public function updateRepository(Request $request, DevelopmentRun $developmentRun, OpenCodeExecutionRunner $runner): RedirectResponse
    {
        $data = $request->validate([
            'repository' => ['required', 'string', 'max:1024'],
        ]);

        $repository = trim($data['repository']);
        if (! is_dir($repository)) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['repository' => 'La ruta del repositorio debe ser una carpeta local válida.'])
                ->withInput();
        }

        DB::transaction(function () use ($developmentRun, $repository, $runner) {
            $developmentRun->update(['repository' => $repository]);

            $buildPlan = $developmentRun->artifacts()->where('type', 'build_plan')->first();
            $executionPrompt = $developmentRun->artifacts()->where('type', 'execution_prompt')->first();
            $alreadyExecuted = $developmentRun->artifacts()->where('type', 'opencode_execution')->exists();

            if ($buildPlan && $executionPrompt && ! $alreadyExecuted) {
                $executionPrompt->update([
                    'body' => $this->executionPromptFor($developmentRun->fresh(), $buildPlan->title, $runner->buildProfile()),
                    'metadata' => ['generator' => 'deterministic', 'version' => 3, 'refreshed_after_repository_update' => true],
                ]);
            }
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeTechnicalBrief(DevelopmentRun $developmentRun, OpenCodeExecutionRunner $runner, StageAgentContract $contract): RedirectResponse
    {
        DB::transaction(function () use ($developmentRun, $runner, $contract) {
            $agents = $runner->stageAgents();

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Plan'],
                [
                    'body' => $contract->render('Plan', $agents['planning'], 'Convertir contexto inicial en brief técnico accionable.', ['Contexto inicial'], ['Leer contexto', 'Detectar restricciones', 'Proponer criterios de aceptación'], ['technical_brief', 'status: completed | blocked | failed', 'risks']),
                    'metadata' => ['stage' => 'plan', 'agent' => $agents['planning']],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'technical_brief'],
                [
                    'title' => 'Brief técnico inicial',
                    'body' => $this->technicalBriefFor($developmentRun),
                    'metadata' => ['generator' => 'deterministic', 'version' => 1],
                    'created_by' => 'system',
                ],
            );

            if ($developmentRun->active_stage === 'contexto') {
                $developmentRun->update(['active_stage' => 'plan', 'status' => 'planning']);
            }
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function returnToContext(DevelopmentRun $developmentRun): RedirectResponse
    {
        if ($developmentRun->active_stage !== 'contexto') {
            $developmentRun->update(['active_stage' => 'contexto', 'status' => 'intake']);
        }

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeImplementationSlices(DevelopmentRun $developmentRun, OpenCodeExecutionRunner $runner, StageAgentContract $contract): RedirectResponse
    {
        $technicalBrief = $developmentRun->artifacts()->where('type', 'technical_brief')->first();

        if (! $technicalBrief) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['implementation_slices' => 'Primero generá el brief técnico.']);
        }

        DB::transaction(function () use ($developmentRun, $technicalBrief, $runner, $contract) {
            $agents = $runner->stageAgents();

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Slices'],
                [
                    'body' => $contract->render('Slices', $agents['slicing'], 'Dividir el brief en slices chicos, verificables y revisables.', ['Brief técnico inicial'], ['Leer brief', 'Definir slices ordenados', 'Mantener cada slice por debajo de carga saludable de revisión'], ['implementation_slices', 'review workload forecast', 'next recommended slice']),
                    'metadata' => ['stage' => 'slices', 'agent' => $agents['slicing']],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'implementation_slices'],
                [
                    'title' => 'Slices de implementación',
                    'body' => $this->implementationSlicesFor($developmentRun, $technicalBrief->title),
                    'metadata' => ['generator' => 'deterministic', 'version' => 1],
                    'created_by' => 'system',
                ],
            );

            if ($developmentRun->active_stage === 'plan') {
                $developmentRun->update(['active_stage' => 'slices', 'status' => 'slicing']);
            }
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function returnToPlan(DevelopmentRun $developmentRun): RedirectResponse
    {
        if ($developmentRun->active_stage === 'slices') {
            $developmentRun->update(['active_stage' => 'plan', 'status' => 'planning']);
        }

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeBuildPlan(DevelopmentRun $developmentRun, OpenCodeExecutionRunner $runner, StageAgentContract $contract): RedirectResponse
    {
        $implementationSlices = $developmentRun->artifacts()->where('type', 'implementation_slices')->first();

        if (! $implementationSlices) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['build_plan' => 'Primero definí los slices de implementación.']);
        }

        DB::transaction(function () use ($developmentRun, $runner, $contract) {
            $agents = $runner->stageAgents();

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Build'],
                [
                    'body' => $contract->render('Build', $agents['build'], 'Ejecutar el slice seleccionado con límites explícitos y artifact de resultado.', ['implementation_slices', 'build_plan', 'execution_prompt'], ['Ejecutar OpenCode con worker Build', 'Respetar restricciones de Git', 'Reportar salida estructurada'], ['opencode_execution', 'status', 'files changed', 'verification suggestion']),
                    'metadata' => ['stage' => 'build', 'agent' => $agents['build']],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'build_plan'],
                [
                    'title' => 'Plan de build inicial',
                    'body' => $this->buildPlanFor(),
                    'metadata' => ['generator' => 'deterministic', 'version' => 1],
                    'created_by' => 'system',
                ],
            );

            if (in_array($developmentRun->active_stage, ['slices', 'build'], true)) {
                $developmentRun->update(['active_stage' => 'build', 'status' => 'ready_for_build']);
            }
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function returnToSlices(DevelopmentRun $developmentRun): RedirectResponse
    {
        if (in_array($developmentRun->active_stage, ['build', 'qa', 'review'], true) && ! $developmentRun->completed_at) {
            $developmentRun->update(['active_stage' => 'slices', 'status' => 'slicing']);
        }

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeExecutionPrompt(DevelopmentRun $developmentRun, OpenCodeExecutionRunner $runner): RedirectResponse
    {
        $buildPlan = $developmentRun->artifacts()->where('type', 'build_plan')->first();

        if (! $buildPlan) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['execution_prompt' => 'Primero prepará el plan de build.']);
        }

        DB::transaction(function () use ($developmentRun, $buildPlan, $runner) {
            $developmentRun->artifacts()->updateOrCreate(
                ['type' => 'execution_prompt'],
                [
                    'title' => 'Prompt de ejecución OpenCode',
                    'body' => $this->executionPromptFor($developmentRun, $buildPlan->title, $runner->buildProfile()),
                    'metadata' => ['generator' => 'deterministic', 'version' => 3, 'stage_agent' => $runner->buildProfile()['stage_agent']],
                    'created_by' => 'system',
                ],
            );

            if ($developmentRun->active_stage === 'build') {
                $developmentRun->update(['active_stage' => 'build', 'status' => 'ready_for_execution']);
            }
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function runOpenCode(DevelopmentRun $developmentRun, DevelopmentRunBackgroundProcess $background): RedirectResponse
    {
        $executionPrompt = $developmentRun->artifacts()->where('type', 'execution_prompt')->first();

        if (! $executionPrompt) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['opencode_execution' => 'Primero prepará el prompt de ejecución.']);
        }

        if ($developmentRun->artifacts()->where('type', 'opencode_execution')->exists()) {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        if ($developmentRun->status === 'build_running') {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $workingDirectory = trim((string) $developmentRun->repository);
        if ($workingDirectory === '' || ! is_dir($workingDirectory)) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['opencode_execution' => 'El repositorio debe ser una ruta local válida antes de ejecutar OpenCode.']);
        }

        DB::transaction(function () use ($developmentRun) {
            $developmentRun->artifacts()->updateOrCreate(
                ['type' => 'build_background_run'],
                [
                    'title' => 'Build en ejecución',
                    'body' => "Build worker iniciado en background.\n\nLa página se actualiza sola mientras OpenCode trabaja.",
                    'metadata' => ['stage' => 'build', 'status' => 'running', 'started_at' => now()->toISOString()],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->update(['active_stage' => 'build', 'status' => 'build_running']);
        });

        $pid = $background->startBuild($developmentRun->fresh());
        $this->recordBackgroundStart($developmentRun, 'build_background_run', $pid, $background->lastStartMetadata());

        return redirect()->route('development-runs.show', $developmentRun);
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
            ."- Repositorio: ".($run->repository ?: 'No definido')."\n"
            ."- Proyecto: ".($run->project ?: 'No definido')."\n"
            ."- Prioridad: ".($run->priority ?: 'No definida');
    }

    private function implementationSlicesFor(DevelopmentRun $run, string $technicalBriefTitle): string
    {
        return "Run: {$run->title}\n"
            ."Punto de partida: {$technicalBriefTitle}\n\n"
            ."Slice 1 — Preparar cambio mínimo\n"
            ."Objetivo: confirmar alcance y ubicar archivos/áreas impactadas.\n"
            ."Criterios: brief revisado, riesgos visibles, sin cambios de Git automáticos.\n\n"
            ."Slice 2 — Implementar comportamiento principal\n"
            ."Objetivo: aplicar el cambio funcional más chico que resuelva el problema.\n"
            ."Criterios: tests o evidencia cubren el caso principal.\n\n"
            ."Slice 3 — QA y refinamiento\n"
            ."Objetivo: validar en entorno local y ajustar bordes detectados.\n"
            ."Criterios: pruebas pasan, evidencia lista para revisión humana.";
    }

    private function buildPlanFor(): string
    {
        return "Slice seleccionado\n"
            ."- Slice 1 — Preparar cambio mínimo\n\n"
            ."Alcance permitido\n"
            ."- Preparar el entorno de implementación para el primer slice.\n"
            ."- Identificar archivos/áreas probables antes de ejecutar OpenCode.\n"
            ."- Mantener Git sin cambios automáticos hasta aprobación humana.\n\n"
            ."Fuera de alcance\n"
            ."- No ejecutar OpenCode todavía.\n"
            ."- No modificar código en esta etapa.\n"
            ."- No correr Playwright ni acciones de Git.\n\n"
            ."Próximo paso\n"
            ."- Ejecutar el slice seleccionado con OpenCode en una etapa Build controlada.";
    }

    /**
     * @param array{orchestrator_agent: string, stage_agent: string, model: string, variant: string} $profile
     */
    private function executionPromptFor(DevelopmentRun $run, string $buildPlanTitle, array $profile): string
    {
        return "EJECUCIÓN NO INTERACTIVA. Tarea completa: ejecutar Slice 1 — Preparar cambio mínimo en modo read-only para este Development Run; no pedir comandos, contexto ni confirmación.\n"
            ."- BUILD WORKER DIRECTO: no uses modo SDD, OpenSpec, Engram ni busques tasks.md/spec/proposal/design.\n"
            ."- Este prompt contiene TODO el contexto disponible para esta ejecución.\n"
            ."- No saludes.\n"
            ."- No respondas '¿En qué puedo ayudarte?'.\n"
            ."- No respondas 'Dame el comando' ni pidas que el usuario describa otra tarea.\n"
            ."- No pidas confirmación al usuario.\n"
            ."- Usá el contexto de este prompt como la tarea completa.\n"
            ."- Ejecutá la tarea indicada o reportá bloqueo concreto en el formato obligatorio.\n"
            ."- Si no corresponde modificar código, decilo explícitamente y explicá por qué.\n\n"
            ."Arquitectura de agentes\n"
            ."- Coordinador del Development Run: {$profile['orchestrator_agent']} — coordina etapas y artifacts, no ejecuta este slice.\n"
            ."- Worker de esta etapa Build: {$profile['stage_agent']} — ejecuta o reporta el slice asignado.\n"
            ."- Modelo: {$profile['model']}\n"
            ."- Esfuerzo/variant: {$profile['variant']}\n"
            ."- Este prompt está dirigido al worker de Build, no al orquestador.\n\n"
            ."Prompt preparado para OpenCode\n"
            ."Run: {$run->title}\n"
            ."Punto de partida: {$buildPlanTitle}\n"
            ."Repositorio objetivo: ".($run->repository ?: 'No definido')."\n"
            ."Proyecto: ".($run->project ?: 'No definido')."\n\n"
            ."Tarea\n"
            ."- Entregable concreto de este slice: producir un reporte read-only de preparación. Si no hay cambios de código que hacer, eso cuenta como completed.\n"
            ."- Ejecutar Slice 1 — Preparar cambio mínimo en modo read-only.\n"
            ."- Confirmar alcance usando el contexto del run.\n"
            ."- Identificar archivos/áreas probablemente impactadas.\n"
            ."- No modificar archivos en este slice preparatorio.\n"
            ."- Si el alcance todavía es insuficiente, reportar Estado: blocked con preguntas concretas.\n\n"
            ."Restricciones obligatorias\n"
            ."- No commitear, stagear, pushear ni cambiar remotos.\n"
            ."- No modificar archivos.\n"
            ."- No ejecutar tests ni Playwright en este slice.\n"
            ."- No esperar más input del usuario durante esta ejecución.\n\n"
            ."Verificación esperada\n"
            ."- Informar que no hubo archivos modificados, salvo que detectes una violación.\n"
            ."- Proponer comandos de test/lint relevantes para un futuro slice de implementación.\n"
            ."- Dejar evidencia suficiente para decidir si se puede implementar.\n\n"
            ."Formato de respuesta obligatorio\n"
            ."Estado: completed | blocked | failed\n"
            ."Resumen:\n"
            ."Archivos modificados:\n"
            ."Verificación sugerida:\n"
            ."Riesgos o dudas:\n\n"
            ."Estado actual\n"
            ."- Este prompt está preparado, pero todavía no se ejecutó OpenCode.";
    }

    public function runQa(DevelopmentRun $developmentRun, DevelopmentRunBackgroundProcess $background): RedirectResponse
    {
        if (! $developmentRun->artifacts()->where('type', 'opencode_execution')->exists()) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['qa_report' => 'Primero ejecutá Build.']);
        }

        if ($developmentRun->artifacts()->where('type', 'qa_report')->exists() && ! in_array($developmentRun->status, ['qa_failed', 'qa_blocked'], true)) {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        if ($developmentRun->status === 'qa_running') {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $workingDirectory = trim((string) $developmentRun->repository);
        if ($workingDirectory === '' || ! is_dir($workingDirectory)) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['qa_report' => 'El repositorio debe ser una ruta local válida antes de ejecutar QA.']);
        }

        DB::transaction(function () use ($developmentRun) {
            $developmentRun->artifacts()->updateOrCreate(
                ['type' => 'qa_background_run'],
                [
                    'title' => 'QA en ejecución',
                    'body' => "QA runner iniciado en background.\n\nLa página se actualiza sola mientras corren las verificaciones.",
                    'metadata' => ['stage' => 'qa', 'status' => 'running', 'started_at' => now()->toISOString()],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->update(['active_stage' => 'qa', 'status' => 'qa_running']);
        });

        $pid = $background->startQa($developmentRun->fresh());
        $this->recordBackgroundStart($developmentRun, 'qa_background_run', $pid, $background->lastStartMetadata());

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function cancelExecution(DevelopmentRun $developmentRun, DevelopmentRunBackgroundProcess $background): RedirectResponse
    {
        if (! in_array($developmentRun->status, ['build_running', 'qa_running'], true)) {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $stage = $developmentRun->status === 'build_running' ? 'build' : 'qa';
        $artifactType = $stage === 'build' ? 'build_background_run' : 'qa_background_run';
        $cancelled = $background->cancel($developmentRun);

        DB::transaction(function () use ($developmentRun, $artifactType, $stage, $cancelled) {
            $artifact = $developmentRun->artifacts()->where('type', $artifactType)->first();
            $metadata = $artifact?->metadata ?? [];

            $developmentRun->artifacts()->updateOrCreate(
                ['type' => $artifactType],
                [
                    'title' => $stage === 'build' ? 'Build cancelado' : 'QA cancelado',
                    'body' => ($stage === 'build' ? 'Build' : 'QA')." cancelado por el usuario.\n\nSe intentó detener el proceso background".($cancelled ? ' correctamente.' : ', pero no se pudo confirmar la señal.'),
                    'metadata' => [...$metadata, 'status' => 'cancelled', 'cancelled_at' => now()->toISOString(), 'cancel_signal_sent' => $cancelled],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->update(['active_stage' => $stage, 'status' => $stage === 'build' ? 'build_cancelled' : 'qa_cancelled']);
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeReview(DevelopmentRun $developmentRun, OpenCodeExecutionRunner $runner, StageAgentContract $contract): RedirectResponse
    {
        $qaReport = $developmentRun->artifacts()->where('type', 'qa_report')->first();

        if (! $qaReport) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['review_report' => 'Primero ejecutá QA.']);
        }

        DB::transaction(function () use ($developmentRun, $runner, $contract, $qaReport) {
            $agents = $runner->stageAgents();

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente Revisión'],
                [
                    'body' => $contract->render('Revisión', $agents['review'], 'Cerrar el Development Run con resumen, evidencia y próximo paso humano.', ['context', 'technical_brief', 'implementation_slices', 'build_plan', 'opencode_execution', 'qa_report'], ['Sintetizar artifacts', 'No ejecutar cambios', 'Marcar cierre local'], ['review_report', 'final_status', 'human handoff']),
                    'metadata' => ['stage' => 'review', 'agent' => $agents['review']],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->artifacts()->firstOrCreate(
                ['type' => 'review_report'],
                [
                    'title' => 'Cierre del Development Run',
                    'body' => $this->reviewReportBody($developmentRun->fresh(['artifacts']), $qaReport),
                    'metadata' => ['agent' => $agents['review'], 'status' => 'completed'],
                    'created_by' => 'review-agent',
                ],
            );

            $developmentRun->update(['active_stage' => 'review', 'status' => 'completed', 'completed_at' => now()]);
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    /**
     * @param array<string, mixed> $startMetadata
     */
    private function recordBackgroundStart(DevelopmentRun $run, string $artifactType, ?int $pid, array $startMetadata): void
    {
        if ($pid === null) {
            return;
        }

        $artifact = $run->artifacts()->where('type', $artifactType)->first();
        if (! $artifact) {
            return;
        }

        $artifact->update([
            'metadata' => [...($artifact->metadata ?? []), ...$startMetadata, 'pid' => $pid],
        ]);
    }

    private function reviewReportBody(DevelopmentRun $run, $qaReport): string
    {
        $artifactSummary = $run->artifacts
            ->reject(fn ($artifact) => $artifact->type === 'review_report')
            ->map(fn ($artifact) => "- {$artifact->title} ({$artifact->type})")
            ->implode("\n");

        return "Cierre del Development Run\n"
            ."- Run: {$run->title}\n"
            ."- Estado final: completed\n"
            ."- Repositorio: ".($run->repository ?: 'No definido')."\n"
            ."- Proyecto: ".($run->project ?: 'No definido')."\n\n"
            ."Artifacts generados\n"
            .($artifactSummary ?: '- No hay artifacts previos.')."\n\n"
            ."Evidencia QA\n"
            ."- {$qaReport->title}\n\n"
            ."Handoff humano\n"
            ."- Revisar artifacts antes de integrar cambios.\n"
            ."- No se realizaron commits, stage, push ni cambios de remotos desde Command Flow.\n"
            ."- Si el resultado requiere integración, hacerlo manualmente con revisión humana.";
    }
}
