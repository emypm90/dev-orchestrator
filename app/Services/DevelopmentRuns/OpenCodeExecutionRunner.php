<?php

namespace App\Services\DevelopmentRuns;

use Symfony\Component\Process\Process;

class OpenCodeExecutionRunner
{
    public function isAvailable(): bool
    {
        $process = new Process(['where', 'opencode']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return array{status: 'completed'|'failed', exit_code: int, output: string}
     */
    public function run(string $workingDirectory, string $prompt): array
    {
        $process = new Process(
            [
                'opencode',
                'run',
                '--agent', $this->buildAgent(),
                '--model', $this->model(),
                '--variant', $this->variant(),
                '--dir', $workingDirectory,
                $this->cliPrompt($prompt),
            ],
            env: $this->nestedOpenCodeEnvironment(),
            timeout: 1800,
        );
        $process->run();

        $exitCode = $process->getExitCode() ?? 1;

        return [
            'status' => $exitCode === 0 ? 'completed' : 'failed',
            'exit_code' => $exitCode,
            'output' => trim($process->getOutput().$process->getErrorOutput()),
        ];
    }

    /**
     * @return array{orchestrator_agent: string, stage_agent: string, model: string, variant: string}
     */
    public function buildProfile(): array
    {
        return [
            'orchestrator_agent' => $this->orchestratorAgent(),
            'stage_agent' => $this->buildAgent(),
            'model' => $this->model(),
            'variant' => $this->variant(),
        ];
    }

    /**
     * @return array{context: string, planning: string, slicing: string, build: string, qa: string, review: string}
     */
    public function stageAgents(): array
    {
        return [
            'context' => 'manual-intake',
            'planning' => 'deterministic-brief-agent',
            'slicing' => 'deterministic-slicing-agent',
            'build' => $this->buildAgent(),
            'qa' => env('DEVELOPMENT_RUN_QA_AGENT', 'local-qa-runner'),
            'review' => 'deterministic-closure-agent',
        ];
    }

    /**
     * @return array<string, false>
     */
    private function nestedOpenCodeEnvironment(): array
    {
        return [
            'OPENCODE_CLIENT' => false,
            'OPENCODE_SERVER_USERNAME' => false,
            'OPENCODE_SERVER_PASSWORD' => false,
        ];
    }

    private function cliPrompt(string $prompt): string
    {
        return "EJECUCIÓN NO INTERACTIVA. BUILD WORKER DIRECTO, NO SDD, NO OPENSPEC, NO ENGRAM, NO BUSCAR tasks.md/spec/proposal/design. Tarea concreta: producir el reporte read-only de preparación del slice con el contexto incluido en este mensaje; si no hay cambios de código, responder completed con archivos modificados: ninguno. No pidas comandos, contexto ni confirmación. Respuesta obligatoria: Estado, Resumen, Archivos modificados, Verificación sugerida, Riesgos o dudas.\n\n{$prompt}";
    }

    private function model(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_MODEL', 'openai/gpt-5.5');
    }

    private function variant(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_VARIANT', 'high');
    }

    private function orchestratorAgent(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_ORCHESTRATOR_AGENT', 'gentle-orchestrator');
    }

    private function buildAgent(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_BUILD_AGENT', 'build');
    }
}
