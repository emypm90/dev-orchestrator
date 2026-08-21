<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunBackgroundProcess;
use App\Services\DevelopmentRuns\DevelopmentRunStaleExecutionDetector;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\StageAgentContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
            'running' => in_array($developmentRun->status, ['plan_running', 'slices_running', 'build_running', 'qa_running', 'review_running'], true),
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
                    'body' => $this->executionPromptFor($developmentRun->fresh(), $buildPlan, $runner->buildProfile()),
                    'metadata' => ['generator' => 'deterministic', 'version' => 3, 'refreshed_after_repository_update' => true],
                ]);
            }
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeTechnicalBrief(DevelopmentRun $developmentRun, DevelopmentRunBackgroundProcess $background): RedirectResponse
    {
        if ($developmentRun->artifacts()->where('type', 'technical_brief')->exists()) {
            if ($developmentRun->active_stage === 'contexto') {
                $developmentRun->update(['active_stage' => 'plan', 'status' => 'planning']);
            }

            return redirect()->route('development-runs.show', $developmentRun);
        }

        if ($developmentRun->status === 'plan_running') {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $this->startAgentStage($developmentRun, $background, 'plan', 'Plan en ejecución', "Plan agent iniciado en background.\n\nLa página se actualiza sola mientras OpenCode genera el brief técnico.");

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function returnToContext(DevelopmentRun $developmentRun): RedirectResponse
    {
        if ($developmentRun->active_stage !== 'contexto') {
            $developmentRun->update(['active_stage' => 'contexto', 'status' => 'intake']);
        }

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeImplementationSlices(DevelopmentRun $developmentRun, DevelopmentRunBackgroundProcess $background): RedirectResponse
    {
        $technicalBrief = $developmentRun->artifacts()->where('type', 'technical_brief')->first();

        if (! $technicalBrief) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['implementation_slices' => 'Primero generá el brief técnico.']);
        }

        if ($developmentRun->artifacts()->where('type', 'implementation_slices')->exists()) {
            if ($developmentRun->active_stage === 'plan') {
                $developmentRun->update(['active_stage' => 'slices', 'status' => 'slicing']);
            }

            return redirect()->route('development-runs.show', $developmentRun);
        }

        if ($developmentRun->status === 'slices_running') {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $this->startAgentStage($developmentRun, $background, 'slices', 'Slices en ejecución', "Slices agent iniciado en background.\n\nLa página se actualiza sola mientras OpenCode define los slices.");

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

        DB::transaction(function () use ($developmentRun, $implementationSlices, $runner, $contract) {
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
                    'body' => $this->buildPlanFor($developmentRun, $implementationSlices->body),
                    'metadata' => ['generator' => 'deterministic', 'version' => 2, 'source_implementation_slices_id' => $implementationSlices->id],
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
                    'body' => $this->executionPromptFor($developmentRun, $buildPlan, $runner->buildProfile()),
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

    private function buildPlanFor(DevelopmentRun $run, string $implementationSlices): string
    {
        $selectedSlice = $this->selectBuildSlice($implementationSlices);

        return "Slice seleccionado\n"
            ."{$selectedSlice}\n\n"
            ."Alcance permitido\n"
            ."- Implementar el slice seleccionado con el cambio mínimo necesario.\n"
            ."- Si el cambio pedido es documental, limitar la edición a documentación, preferentemente README.\n"
            ."- Mantener Git sin cambios automáticos hasta aprobación humana.\n\n"
            ."Fuera de alcance\n"
            ."- No ejecutar OpenCode todavía.\n"
            ."- No commitear, stagear, pushear ni cambiar remotos.\n"
            ."- No correr Playwright.\n\n"
            ."Contexto del run\n"
            ."- {$run->initial_context}\n\n"
            ."Próximo paso\n"
            .'- Ejecutar el slice seleccionado con OpenCode en una etapa Build controlada.';
    }

    private function selectBuildSlice(string $implementationSlices): string
    {
        $sections = preg_split('/(?=^#{0,3}\s*Slice\s+\d+\s*[:—-])/mi', trim($implementationSlices)) ?: [];
        $sections = collect($sections)
            ->map(fn (string $section) => trim($section))
            ->filter();

        $implementable = $sections->first(fn (string $section) => preg_match('/\b(agregar|implementar|editar|modificar|ajustar|aplicar|crear|actualizar)\b/i', $section)
            && ! preg_match('/\b(solo lectura|validaci[oó]n manual|entrega para revisi[oó]n|preparar|ubicar)\b/i', mb_substr($section, 0, 160)));

        return $implementable ?: ($sections->first() ?: trim($implementationSlices));
    }

    /**
     * @param  array{orchestrator_agent: string, stage_agent: string, model: string, variant: string}  $profile
     */
    private function executionPromptFor(DevelopmentRun $run, $buildPlan, array $profile): string
    {
        return "EJECUCIÓN NO INTERACTIVA. Tarea completa: ejecutar el slice seleccionado para este Development Run; no pedir comandos, contexto ni confirmación.\n"
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
            ."Punto de partida: {$buildPlan->title}\n"
            .'Repositorio objetivo: '.($run->repository ?: 'No definido')."\n"
            .'Proyecto: '.($run->project ?: 'No definido')."\n\n"
            ."Contexto inicial\n"
            ."{$run->initial_context}\n\n"
            ."Plan de build seleccionado\n"
            ."{$buildPlan->body}\n\n"
            ."Tarea\n"
            ."- Entregable concreto de este slice: aplicar el cambio mínimo indicado por el plan de build.\n"
            ."- Si el cambio es documental, editá la documentación mínima necesaria.\n"
            ."- Si no corresponde modificar archivos, explicá por qué con Estado: blocked o completed según corresponda.\n"
            ."- Si el alcance todavía es insuficiente, reportar Estado: blocked con preguntas concretas.\n\n"
            ."Restricciones obligatorias\n"
            ."- No commitear, stagear, pushear ni cambiar remotos.\n"
            ."- No ejecutar tests ni Playwright en este slice.\n"
            ."- No esperar más input del usuario durante esta ejecución.\n\n"
            ."Verificación esperada\n"
            ."- Informar archivos modificados.\n"
            ."- Proponer comandos de test/lint relevantes para un futuro slice de implementación.\n"
            ."- Dejar evidencia suficiente para QA.\n\n"
            ."Formato de respuesta obligatorio\n"
            ."Estado: completed | blocked | failed\n"
            ."Resumen:\n"
            ."Archivos modificados:\n"
            ."Verificación sugerida:\n"
            ."Riesgos o dudas:\n\n"
            ."Estado actual\n"
            .'- Este prompt está preparado, pero todavía no se ejecutó OpenCode.';
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
        if (! in_array($developmentRun->status, ['plan_running', 'slices_running', 'build_running', 'qa_running', 'review_running'], true)) {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $stage = str($developmentRun->status)->before('_running')->toString();
        $artifactType = "{$stage}_background_run";
        $cancelled = $background->cancel($developmentRun);

        DB::transaction(function () use ($developmentRun, $artifactType, $stage, $cancelled) {
            $artifact = $developmentRun->artifacts()->where('type', $artifactType)->first();
            $metadata = $artifact?->metadata ?? [];

            $developmentRun->artifacts()->updateOrCreate(
                ['type' => $artifactType],
                [
                    'title' => $this->stageLabel($stage).' cancelado',
                    'body' => $this->stageLabel($stage)." cancelado por el usuario.\n\nSe intentó detener el proceso background".($cancelled ? ' correctamente.' : ', pero no se pudo confirmar la señal.'),
                    'metadata' => [...$metadata, 'status' => 'cancelled', 'cancelled_at' => now()->toISOString(), 'cancel_signal_sent' => $cancelled],
                    'created_by' => 'system',
                ],
            );

            $developmentRun->update(['active_stage' => $stage, 'status' => "{$stage}_cancelled"]);
        });

        return redirect()->route('development-runs.show', $developmentRun);
    }

    public function storeReview(DevelopmentRun $developmentRun, DevelopmentRunBackgroundProcess $background): RedirectResponse
    {
        $qaReport = $developmentRun->artifacts()->where('type', 'qa_report')->first();

        if (! $qaReport) {
            return redirect()
                ->route('development-runs.show', $developmentRun)
                ->withErrors(['review_report' => 'Primero ejecutá QA.']);
        }

        if ($developmentRun->artifacts()->where('type', 'review_report')->exists()) {
            if ($developmentRun->completed_at === null) {
                $developmentRun->update(['active_stage' => 'review', 'status' => 'completed', 'completed_at' => now()]);
            }

            return redirect()->route('development-runs.show', $developmentRun);
        }

        if ($developmentRun->status === 'review_running') {
            return redirect()->route('development-runs.show', $developmentRun);
        }

        $this->startAgentStage($developmentRun, $background, 'review', 'Revisión en ejecución', "Review agent iniciado en background.\n\nLa página se actualiza sola mientras OpenCode genera el cierre del run.");

        return redirect()->route('development-runs.show', $developmentRun);
    }

    private function startAgentStage(DevelopmentRun $run, DevelopmentRunBackgroundProcess $background, string $stage, string $title, string $body): void
    {
        $artifactType = "{$stage}_background_run";

        DB::transaction(function () use ($run, $stage, $artifactType, $title, $body) {
            $run->artifacts()->updateOrCreate(
                ['type' => $artifactType],
                [
                    'title' => $title,
                    'body' => $body,
                    'metadata' => ['stage' => $stage, 'status' => 'running', 'started_at' => now()->toISOString()],
                    'created_by' => 'system',
                ],
            );

            $run->update(['active_stage' => $stage, 'status' => "{$stage}_running"]);
        });

        $freshRun = $run->fresh();
        $pid = match ($stage) {
            'plan' => $background->startPlan($freshRun),
            'slices' => $background->startSlices($freshRun),
            'review' => $background->startReview($freshRun),
            default => null,
        };

        $this->recordBackgroundStart($run, $artifactType, $pid, $background->lastStartMetadata());
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'plan' => 'Plan',
            'slices' => 'Slices',
            'build' => 'Build',
            'qa' => 'QA',
            'review' => 'Revisión',
            default => ucfirst($stage),
        };
    }

    /**
     * @param  array<string, mixed>  $startMetadata
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
}
