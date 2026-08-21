<?php

namespace App\Services\DevelopmentRuns;

use App\Models\DevelopmentRun;
use Illuminate\Support\Facades\DB;
use Throwable;

class DevelopmentRunExecutionService
{
    public function executeBuild(DevelopmentRun $run, OpenCodeExecutionRunner $runner): void
    {
        if ($run->fresh()->status !== 'build_running') {
            return;
        }

        $executionPrompt = $run->artifacts()->where('type', 'execution_prompt')->first();
        if (! $executionPrompt || $run->artifacts()->where('type', 'opencode_execution')->exists()) {
            return;
        }

        $workingDirectory = trim((string) $run->repository);
        if ($workingDirectory === '' || ! is_dir($workingDirectory)) {
            $this->storeOpenCodeExecutionResult($run, 'Ejecución OpenCode bloqueada', "El repositorio debe ser una ruta local válida antes de ejecutar OpenCode.\n\nNo se ejecutó ningún cambio.", ['status' => 'blocked', 'exit_code' => null], 'execution_blocked');

            return;
        }

        if (! $runner->isAvailable()) {
            $this->storeOpenCodeExecutionResult($run, 'Ejecución OpenCode bloqueada', "OpenCode CLI no está disponible en PATH.\n\nNo se ejecutó ningún cambio.", ['status' => 'blocked', 'exit_code' => null], 'execution_blocked');

            return;
        }

        $profile = $runner->buildProfile();

        try {
            $result = $runner->run($workingDirectory, $executionPrompt->body);
        } catch (Throwable $exception) {
            $result = ['status' => 'failed', 'exit_code' => 1, 'output' => $exception->getMessage()];
        }

        if ($run->fresh()->status !== 'build_running') {
            return;
        }

        $this->storeOpenCodeExecutionResult(
            $run,
            $result['status'] === 'completed' ? 'Ejecución OpenCode completada' : 'Ejecución OpenCode fallida',
            $this->openCodeExecutionBody($workingDirectory, $result, $profile),
            ['status' => $result['status'], 'exit_code' => $result['exit_code'], ...$profile],
            $result['status'] === 'completed' ? 'build_executed' : 'execution_failed',
            $result['status'] === 'completed' ? 'qa' : 'build',
        );
    }

    public function executeQa(DevelopmentRun $run, QaExecutionRunner $runner, OpenCodeExecutionRunner $openCodeRunner, StageAgentContract $contract): void
    {
        if ($run->fresh()->status !== 'qa_running') {
            return;
        }

        if (! $run->artifacts()->where('type', 'opencode_execution')->exists()) {
            return;
        }

        if ($run->artifacts()->where('type', 'qa_report')->exists() && ! in_array($run->status, ['qa_failed', 'qa_blocked', 'qa_running'], true)) {
            return;
        }

        $workingDirectory = trim((string) $run->repository);
        if ($workingDirectory === '' || ! is_dir($workingDirectory)) {
            $result = ['status' => 'blocked', 'exit_code' => null, 'command' => null, 'output' => 'El repositorio debe ser una ruta local válida antes de ejecutar QA.'];
        } else {
            $result = $runner->run($workingDirectory);
        }

        if ($run->fresh()->status !== 'qa_running') {
            return;
        }

        $agents = $openCodeRunner->stageAgents();
        $qaReport = $this->qaReportResultFor($run, $workingDirectory, $result, $openCodeRunner);
        $nextStatus = match ($result['status']) {
            'passed' => 'qa_passed',
            'failed' => 'qa_failed',
            default => 'qa_blocked',
        };
        $nextStage = $result['status'] === 'passed' ? 'review' : 'qa';

        DB::transaction(function () use ($run, $contract, $agents, $result, $qaReport, $nextStatus, $nextStage) {
            $run->artifacts()->firstOrCreate(
                ['type' => 'stage_contract', 'title' => 'Contrato agente QA'],
                [
                    'body' => $contract->render('QA', $agents['qa'], 'Validar el resultado de Build con comandos seguros y evidencia reproducible.', ['opencode_execution', 'repository', 'QA runner output'], ['Ejecutar comando de QA detectado o configurado', 'Capturar salida', 'Ejecutar OpenCode con agente QA si está disponible', 'Interpretar evidencia sin modificar archivos', 'Usar fallback determinístico si el agente no responde'], ['qa_report', 'command', 'exit_code', 'evidence', 'diagnosis', 'status: passed | failed | blocked']),
                    'metadata' => ['stage' => 'qa', 'agent' => $agents['qa']],
                    'created_by' => 'system',
                ],
            );

            $run->artifacts()->updateOrCreate(
                ['type' => 'qa_report'],
                [
                    'title' => $result['status'] === 'passed' ? 'QA aprobado' : ($result['status'] === 'failed' ? 'QA falló' : 'QA bloqueado'),
                    'body' => $qaReport['body'],
                    'metadata' => $qaReport['metadata'],
                    'created_by' => $qaReport['created_by'],
                ],
            );

            $this->markBackgroundArtifactFinished($run, 'qa_background_run', $result['status']);

            $run->update(['active_stage' => $nextStage, 'status' => $nextStatus]);
        });
    }

    /**
     * @param array{status: 'completed'|'failed', exit_code: int, output: string} $result
     * @param array{orchestrator_agent: string, stage_agent: string, model: string, variant: string} $profile
     */
    private function openCodeExecutionBody(string $workingDirectory, array $result, array $profile): string
    {
        return "Resultado de OpenCode\n"
            ."- Estado: {$result['status']}\n"
            ."- Exit code: {$result['exit_code']}\n"
            ."- Directorio: {$workingDirectory}\n\n"
            ."Perfil de agentes\n"
            ."- Coordinador: {$profile['orchestrator_agent']}\n"
            ."- Worker Build: {$profile['stage_agent']}\n"
            ."- Modelo: {$profile['model']}\n"
            ."- Variant: {$profile['variant']}\n\n"
            ."Salida\n"
            .($result['output'] !== '' ? $result['output'] : 'OpenCode no devolvió salida.');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function storeOpenCodeExecutionResult(DevelopmentRun $run, string $title, string $body, array $metadata, string $status, string $activeStage = 'build'): void
    {
        DB::transaction(function () use ($run, $title, $body, $metadata, $status, $activeStage) {
            $run->artifacts()->firstOrCreate(
                ['type' => 'opencode_execution'],
                ['title' => $title, 'body' => $body, 'metadata' => $metadata, 'created_by' => 'opencode'],
            );

            $this->markBackgroundArtifactFinished($run, 'build_background_run', $metadata['status'] ?? 'completed');

            $run->update(['active_stage' => $activeStage, 'status' => $status]);
        });
    }

    private function markBackgroundArtifactFinished(DevelopmentRun $run, string $artifactType, string $status): void
    {
        $artifact = $run->artifacts()->where('type', $artifactType)->first();
        if (! $artifact) {
            return;
        }

        $stage = $artifactType === 'build_background_run' ? 'Build' : 'QA';
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
     * @param array{status: 'passed'|'failed'|'blocked', exit_code: int|null, command: string|null, output: string} $result
     * @return array{body: string, metadata: array<string, mixed>, created_by: string}
     */
    private function qaReportResultFor(DevelopmentRun $run, string $workingDirectory, array $result, OpenCodeExecutionRunner $openCodeRunner): array
    {
        $profile = $openCodeRunner->qaProfile();
        $fallback = fn (string $reason, ?int $agentExitCode = null, string $agentOutput = ''): array => [
            'body' => $this->qaReportBody($workingDirectory, $result),
            'metadata' => [
                'generator' => 'deterministic',
                'fallback' => true,
                'fallback_reason' => $reason,
                'status' => $result['status'],
                'exit_code' => $result['exit_code'],
                'command' => $result['command'],
                'agent_exit_code' => $agentExitCode,
                'opencode_output' => $agentOutput !== '' ? substr($agentOutput, 0, 2000) : null,
                ...$profile,
            ],
            'created_by' => 'qa-agent',
        ];

        if (! $openCodeRunner->isAvailable()) {
            return $fallback('opencode_unavailable');
        }

        try {
            $agentResult = $openCodeRunner->runQaAnalysis($workingDirectory, $this->qaAnalysisPromptFor($run, $workingDirectory, $result));
        } catch (Throwable $exception) {
            return $fallback('opencode_exception', 1, $exception->getMessage());
        }

        if ($agentResult['status'] !== 'completed' || trim($agentResult['output']) === '') {
            return $fallback('opencode_failed', $agentResult['exit_code'], $agentResult['output']);
        }

        return [
            'body' => trim($agentResult['output']),
            'metadata' => [
                'generator' => 'opencode',
                'fallback' => false,
                'status' => $result['status'],
                'exit_code' => $result['exit_code'],
                'command' => $result['command'],
                'agent_exit_code' => $agentResult['exit_code'],
                'working_directory' => $workingDirectory,
                ...$profile,
            ],
            'created_by' => 'opencode',
        ];
    }

    /**
     * @param array{status: 'passed'|'failed'|'blocked', exit_code: int|null, command: string|null, output: string} $result
     */
    private function qaAnalysisPromptFor(DevelopmentRun $run, string $workingDirectory, array $result): string
    {
        return "Development Run\n"
            ."Título: {$run->title}\n"
            ."Repositorio: {$workingDirectory}\n\n"
            ."Resultado objetivo del runner local\n"
            ."- Estado: {$result['status']}\n"
            ."- Comando: ".($result['command'] ?: 'No ejecutado')."\n"
            ."- Exit code: ".($result['exit_code'] === null ? 'N/A' : $result['exit_code'])."\n\n"
            ."Evidencia cruda\n{$result['output']}\n\n"
            ."Analizá esta evidencia y generá el reporte QA. No modifiques archivos. No ejecutes Git. No ejecutes comandos adicionales.";
    }

    /**
     * @param array{status: 'passed'|'failed'|'blocked', exit_code: int|null, command: string|null, output: string} $result
     */
    private function qaReportBody(string $workingDirectory, array $result): string
    {
        return "Resultado QA\n"
            ."- Estado: {$result['status']}\n"
            ."- Directorio: {$workingDirectory}\n"
            ."- Comando: ".($result['command'] ?: 'No ejecutado')."\n"
            ."- Exit code: ".($result['exit_code'] === null ? 'N/A' : $result['exit_code'])."\n\n"
            ."Evidencia\n"
            .($result['output'] !== '' ? $result['output'] : 'El comando no devolvió salida.')."\n\n"
            ."Decisión del orquestador\n"
            .($result['status'] === 'passed' ? '- QA aprobado. El run puede avanzar a Revisión.' : '- QA no aprobado. Revisar evidencia antes de cerrar el run.');
    }
}
