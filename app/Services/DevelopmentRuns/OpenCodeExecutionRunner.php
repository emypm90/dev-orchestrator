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
        return $this->runStage($this->buildAgent(), $workingDirectory, $this->buildCliPrompt($prompt));
    }

    /**
     * @return array{status: 'completed'|'failed', exit_code: int, output: string}
     */
    public function runPlanning(string $workingDirectory, string $prompt): array
    {
        return $this->runStage($this->planningAgent(), $workingDirectory, $this->planningCliPrompt($prompt));
    }

    /**
     * @return array{status: 'completed'|'failed', exit_code: int, output: string}
     */
    public function runSlicing(string $workingDirectory, string $prompt): array
    {
        return $this->runStage($this->slicingAgent(), $workingDirectory, $this->slicingCliPrompt($prompt));
    }

    /**
     * @return array{status: 'completed'|'failed', exit_code: int, output: string}
     */
    public function runReview(string $workingDirectory, string $prompt): array
    {
        return $this->runStage($this->reviewAgent(), $workingDirectory, $this->reviewCliPrompt($prompt));
    }

    /**
     * @return array{status: 'completed'|'failed', exit_code: int, output: string}
     */
    public function runQaAnalysis(string $workingDirectory, string $prompt): array
    {
        return $this->runStage($this->qaAgent(), $workingDirectory, $this->qaCliPrompt($prompt));
    }

    /**
     * @return array{status: 'completed'|'failed', exit_code: int, output: string}
     */
    private function runStage(string $agent, string $workingDirectory, string $prompt): array
    {
        $process = new Process(
            [
                'opencode',
                'run',
                '--agent', $agent,
                '--model', $this->model(),
                '--variant', $this->variant(),
                '--dir', $workingDirectory,
                $prompt,
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
     * @return array{orchestrator_agent: string, stage_agent: string, model: string, variant: string}
     */
    public function planningProfile(): array
    {
        return [
            'orchestrator_agent' => $this->orchestratorAgent(),
            'stage_agent' => $this->planningAgent(),
            'model' => $this->model(),
            'variant' => $this->variant(),
        ];
    }

    /**
     * @return array{orchestrator_agent: string, stage_agent: string, model: string, variant: string}
     */
    public function slicingProfile(): array
    {
        return [
            'orchestrator_agent' => $this->orchestratorAgent(),
            'stage_agent' => $this->slicingAgent(),
            'model' => $this->model(),
            'variant' => $this->variant(),
        ];
    }

    /**
     * @return array{orchestrator_agent: string, stage_agent: string, model: string, variant: string}
     */
    public function reviewProfile(): array
    {
        return [
            'orchestrator_agent' => $this->orchestratorAgent(),
            'stage_agent' => $this->reviewAgent(),
            'model' => $this->model(),
            'variant' => $this->variant(),
        ];
    }

    /**
     * @return array{orchestrator_agent: string, stage_agent: string, model: string, variant: string}
     */
    public function qaProfile(): array
    {
        return [
            'orchestrator_agent' => $this->orchestratorAgent(),
            'stage_agent' => $this->qaAgent(),
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
            'planning' => $this->planningAgent(),
            'slicing' => $this->slicingAgent(),
            'build' => $this->buildAgent(),
            'qa' => $this->qaAgent(),
            'review' => $this->reviewAgent(),
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

    private function buildCliPrompt(string $prompt): string
    {
        return "EJECUCIÓN NO INTERACTIVA. BUILD WORKER DIRECTO, NO SDD, NO OPENSPEC, NO ENGRAM, NO BUSCAR tasks.md/spec/proposal/design. Tarea concreta: producir el reporte read-only de preparación del slice con el contexto incluido en este mensaje; si no hay cambios de código, responder completed con archivos modificados: ninguno. No pidas comandos, contexto ni confirmación. Respuesta obligatoria: Estado, Resumen, Archivos modificados, Verificación sugerida, Riesgos o dudas.\n\n{$prompt}";
    }

    private function planningCliPrompt(string $prompt): string
    {
        return "EJECUCIÓN NO INTERACTIVA. PLAN AGENT DIRECTO. NO MODIFICAR ARCHIVOS, NO GIT, NO SDD, NO OPENSPEC, NO ENGRAM. Tarea concreta: convertir el contexto del Development Run en un brief técnico accionable. No pidas comandos, contexto ni confirmación. Respondé solo con el contenido del brief, en español, usando secciones claras: Objetivo, Contexto relevante, Restricciones detectadas, Plan inicial, Criterios de aceptación iniciales, Riesgos o dudas.\n\n{$prompt}";
    }

    private function slicingCliPrompt(string $prompt): string
    {
        return "EJECUCIÓN NO INTERACTIVA. SLICES AGENT DIRECTO. NO MODIFICAR ARCHIVOS, NO GIT, NO SDD, NO OPENSPEC, NO ENGRAM. Tarea concreta: convertir el brief técnico del Development Run en slices chicos, ordenados, verificables y revisables. No pidas comandos, contexto ni confirmación. Respondé solo con el contenido de los slices, en español, usando secciones claras por slice e incluyendo objetivo, alcance, criterios y riesgo de revisión.\n\n{$prompt}";
    }

    private function reviewCliPrompt(string $prompt): string
    {
        return "EJECUCIÓN NO INTERACTIVA. REVIEW AGENT DIRECTO. NO MODIFICAR ARCHIVOS, NO GIT, NO SDD, NO OPENSPEC, NO ENGRAM. Tarea concreta: sintetizar los artifacts del Development Run y generar un cierre local con evidencia, estado final y handoff humano. No pidas comandos, contexto ni confirmación. Respondé solo con el reporte de cierre, en español, usando secciones claras: Cierre del Development Run, Artifacts generados, Evidencia QA, Riesgos o dudas, Handoff humano.\n\n{$prompt}";
    }

    private function qaCliPrompt(string $prompt): string
    {
        return "EJECUCIÓN NO INTERACTIVA. QA AGENT DIRECTO. NO MODIFICAR ARCHIVOS, NO GIT, NO SDD, NO OPENSPEC, NO ENGRAM. Tarea concreta: analizar la evidencia cruda del runner QA local y producir un reporte reproducible con diagnóstico, decisión y próximos pasos. No pidas comandos, contexto ni confirmación. No cambies el resultado objetivo del runner: si el runner falló, reportá fallido; si pasó, reportá aprobado. Respondé solo con el reporte QA, en español, usando secciones claras: Resultado QA, Comando, Evidencia, Diagnóstico, Riesgos o dudas, Decisión del orquestador.\n\n{$prompt}";
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

    private function planningAgent(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_PLAN_AGENT', 'plan');
    }

    private function slicingAgent(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_SLICES_AGENT', 'slices');
    }

    private function reviewAgent(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_REVIEW_AGENT', 'review');
    }

    private function qaAgent(): string
    {
        return env('DEVELOPMENT_RUN_OPENCODE_QA_AGENT', env('DEVELOPMENT_RUN_QA_AGENT', 'qa'));
    }
}
