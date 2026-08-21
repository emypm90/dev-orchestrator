<?php

namespace Tests\Feature;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunBackgroundProcess;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\QaExecutionRunner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function startPlan(DevelopmentRun $run): ?int
            {
                return 1111;
            }

            public function startSlices(DevelopmentRun $run): ?int
            {
                return 2222;
            }

            public function startBuild(DevelopmentRun $run): ?int
            {
                return 3333;
            }

            public function startQa(DevelopmentRun $run): ?int
            {
                return 4444;
            }

            public function startReview(DevelopmentRun $run): ?int
            {
                return 5555;
            }

            public function isRunning(?int $pid): bool
            {
                return in_array($pid, [1111, 2222, 3333, 4444, 5555], true);
            }

            public function lastStartMetadata(): array
            {
                return ['log_path' => 'storage/logs/stage.log', 'error_log_path' => 'storage/logs/stage.err.log', 'php_executable' => 'php.exe'];
            }
        });
    }

    public function test_create_page_renders_command_flow_intake_copy(): void
    {
        $this->get(route('development-runs.create'))
            ->assertOk()
            ->assertSee('Arranquemos por el')
            ->assertSee('contexto.')
            ->assertSee('Contexto inicial');
    }

    public function test_store_creates_an_intake_run_and_its_initial_context_artifact(): void
    {
        $response = $this->post(route('development-runs.store'), [
            'title' => 'Documentar el flujo',
            'initial_context' => 'Necesitamos explicar el inicio de Development Runs.',
            'repository' => 'personal-dev-orchestrator',
            'project' => 'Command Flow',
            'priority' => 'normal',
        ]);

        $run = DevelopmentRun::firstOrFail();

        $response->assertRedirect(route('development-runs.show', $run));
        $this->assertDatabaseHas('development_runs', ['title' => 'Documentar el flujo', 'status' => 'intake', 'active_stage' => 'contexto']);
        $this->assertDatabaseHas('development_run_artifacts', ['development_run_id' => $run->id, 'type' => 'context', 'title' => 'Contexto inicial']);
        $this->assertSame('Necesitamos explicar el inicio de Development Runs.', $run->artifacts()->firstOrFail()->body);
    }

    public function test_show_renders_run_context_and_command_flow_stage(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'started_at' => now()]);
        $run->artifacts()->create(['type' => 'context', 'title' => 'Contexto inicial', 'body' => $run->initial_context]);

        $this->get(route('development-runs.show', $run))
            ->assertOk()
            ->assertSee('Resolver acceso')
            ->assertSee('Validar el permiso pendiente.')
            ->assertSee('Intake / Contexto')
            ->assertSee('Contexto inicial');
    }

    public function test_status_endpoint_reports_run_progress_for_polling(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function isRunning(?int $pid): bool
            {
                return $pid === 1234;
            }

            public function lastStartMetadata(): array
            {
                return ['log_path' => 'storage/logs/build.log', 'error_log_path' => 'storage/logs/build.err.log', 'php_executable' => 'php.exe'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'build_running',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'build_background_run', 'title' => 'Build en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 1234, 'status' => 'running']]);

        $this->getJson(route('development-runs.status', $run))
            ->assertOk()
            ->assertJsonPath('id', $run->id)
            ->assertJsonPath('status', 'build_running')
            ->assertJsonPath('active_stage', 'build')
            ->assertJsonPath('running', true)
            ->assertJsonPath('completed', false)
            ->assertJsonPath('artifacts.build_background_run', 1);
    }

    public function test_running_stage_uses_javascript_polling_instead_of_meta_refresh(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function isRunning(?int $pid): bool
            {
                return $pid === 5678;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 5678, 'status' => 'running']]);

        $this->get(route('development-runs.show', $run))
            ->assertOk()
            ->assertSee(route('development-runs.status', $run), false)
            ->assertSee('window.setInterval', false)
            ->assertSee('QA está corriendo en background')
            ->assertDontSee('http-equiv="refresh"', false);
    }

    public function test_status_endpoint_recovers_a_stale_build_execution(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function isRunning(?int $pid): bool
            {
                return false;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'build_running',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Prompt listo.']);
        $run->artifacts()->create(['type' => 'build_background_run', 'title' => 'Build en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 9999, 'status' => 'running']]);

        $this->getJson(route('development-runs.status', $run))
            ->assertOk()
            ->assertJsonPath('status', 'build_interrupted')
            ->assertJsonPath('active_stage', 'build')
            ->assertJsonPath('running', false);

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'build_interrupted', 'active_stage' => 'build']);
        $artifact = $run->artifacts()->where('type', 'build_background_run')->firstOrFail();
        $this->assertSame('Build interrumpido', $artifact->title);
        $this->assertSame('interrupted', $artifact->metadata['status']);
        $this->assertFalse($artifact->metadata['pid_was_running']);
    }

    public function test_show_recovers_a_stale_qa_execution_and_offers_retry(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function isRunning(?int $pid): bool
            {
                return false;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 9998, 'status' => 'running']]);

        $this->get(route('development-runs.show', $run))
            ->assertOk()
            ->assertSee('QA quedó interrumpido')
            ->assertSee('Reintentar QA')
            ->assertDontSee('Cancelar ejecución');

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'qa_interrupted', 'active_stage' => 'qa']);
        $artifact = $run->artifacts()->where('type', 'qa_background_run')->firstOrFail();
        $this->assertSame('QA interrumpido', $artifact->title);
        $this->assertSame('interrupted', $artifact->metadata['status']);
    }

    public function test_status_endpoint_does_not_mark_finished_build_as_interrupted_when_pid_is_gone(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function isRunning(?int $pid): bool
            {
                return false;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'build_running',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'build_background_run', 'title' => 'Build en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 9997, 'status' => 'running']]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.', 'metadata' => ['status' => 'completed']]);

        $this->getJson(route('development-runs.status', $run))
            ->assertOk()
            ->assertJsonPath('status', 'build_executed')
            ->assertJsonPath('active_stage', 'qa')
            ->assertJsonPath('running', false);

        $artifact = $run->artifacts()->where('type', 'build_background_run')->firstOrFail();
        $this->assertSame('Build completado', $artifact->title);
        $this->assertSame('completed', $artifact->metadata['status']);
        $this->assertArrayHasKey('finished_at', $artifact->metadata);
    }

    public function test_status_endpoint_does_not_mark_finished_qa_as_interrupted_when_pid_is_gone(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function isRunning(?int $pid): bool
            {
                return false;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 9996, 'status' => 'running']]);
        $run->artifacts()->create(['type' => 'qa_report', 'title' => 'QA aprobado', 'body' => '142 passed.', 'metadata' => ['status' => 'passed']]);

        $this->getJson(route('development-runs.status', $run))
            ->assertOk()
            ->assertJsonPath('status', 'qa_passed')
            ->assertJsonPath('active_stage', 'review')
            ->assertJsonPath('running', false);

        $artifact = $run->artifacts()->where('type', 'qa_background_run')->firstOrFail();
        $this->assertSame('QA completado', $artifact->title);
        $this->assertSame('completed', $artifact->metadata['status']);
        $this->assertArrayHasKey('finished_at', $artifact->metadata);
    }

    public function test_show_offers_to_start_the_technical_brief_and_return_home_before_it_is_generated(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'started_at' => now()]);

        $this->get(route('development-runs.show', $run))
            ->assertOk()
            ->assertSee('Generar brief')
            ->assertDontSee('Comenzar')
            ->assertDontSee('Generar brief técnico')
            ->assertSee('Volver')
            ->assertSee(route('home'));
    }

    public function test_generating_a_technical_brief_starts_plan_in_background(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente. No tocar la configuración de producción.',
            'repository' => 'personal-dev-orchestrator',
            'project' => 'Command Flow',
            'priority' => 'alta',
            'status' => 'intake',
            'active_stage' => 'contexto',
            'started_at' => now(),
        ]);

        $this->post(route('development-runs.technical-brief.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'plan_background_run',
            'title' => 'Plan en ejecución',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'plan_running', 'active_stage' => 'plan']);
        $background = $run->artifacts()->where('type', 'plan_background_run')->firstOrFail();
        $this->assertSame(1111, $background->metadata['pid']);
        $this->get(route('development-runs.show', $run))
            ->assertSee('Plan está corriendo en background')
            ->assertDontSee('Comenzar')
            ->assertDontSee('Definir slices')
            ->assertSee('Volver')
            ->assertDontSee('Volver a contexto');
    }

    public function test_generating_a_technical_brief_uses_the_opencode_plan_agent_when_available(): void
    {
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runPlanning(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'completed', 'exit_code' => 0, 'output' => "Objetivo\n- Brief desde OpenCode Plan\n\nContexto relevante\n- Contexto procesado por agente real."];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => base_path(),
            'project' => 'Command Flow',
            'status' => 'intake',
            'active_stage' => 'contexto',
            'started_at' => now(),
        ]);

        $this->post(route('development-runs.technical-brief.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->artisan('development-run:execute-plan', ['run' => $run->id])
            ->assertSuccessful();

        $brief = $run->artifacts()->where('type', 'technical_brief')->firstOrFail();
        $this->assertSame('opencode', $brief->created_by);
        $this->assertSame('opencode', $brief->metadata['generator']);
        $this->assertFalse($brief->metadata['fallback']);
        $this->assertSame('plan', $brief->metadata['stage_agent']);
        $this->assertStringContainsString('Brief desde OpenCode Plan', $brief->body);
    }

    public function test_generating_a_technical_brief_falls_back_when_the_plan_agent_fails(): void
    {
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runPlanning(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'failed', 'exit_code' => 1, 'output' => 'Agent failed.'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'intake',
            'active_stage' => 'contexto',
            'started_at' => now(),
        ]);

        $this->post(route('development-runs.technical-brief.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->artisan('development-run:execute-plan', ['run' => $run->id])
            ->assertSuccessful();

        $brief = $run->artifacts()->where('type', 'technical_brief')->firstOrFail();
        $this->assertSame('system', $brief->created_by);
        $this->assertSame('deterministic', $brief->metadata['generator']);
        $this->assertTrue($brief->metadata['fallback']);
        $this->assertSame('opencode_failed', $brief->metadata['fallback_reason']);
        $this->assertSame(1, $brief->metadata['exit_code']);
        $this->assertStringContainsString('Objetivo', $brief->body);
    }

    public function test_generating_a_technical_brief_twice_does_not_create_duplicates(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'started_at' => now()]);

        $this->post(route('development-runs.technical-brief.store', $run));
        $this->artisan('development-run:execute-plan', ['run' => $run->id])->assertSuccessful();
        $this->post(route('development-runs.technical-brief.store', $run));

        $this->assertSame(1, $run->artifacts()->where('type', 'technical_brief')->count());
    }

    public function test_repository_path_can_be_updated_when_it_is_a_valid_local_directory(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'development-run-repo-'.uniqid();
        mkdir($directory);
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'repository' => 'checkout-demo', 'started_at' => now()]);

        $this->patch(route('development-runs.repository.update', $run), ['repository' => $directory])
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'repository' => $directory]);
    }

    public function test_updating_repository_refreshes_pending_execution_prompt(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'development-run-refresh-repo-'.uniqid();
        mkdir($directory);
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => 'checkout-demo',
            'project' => 'Command Flow',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'build_plan', 'title' => 'Plan de build inicial', 'body' => 'El plan ya fue generado.']);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Repositorio objetivo: checkout-demo']);

        $this->patch(route('development-runs.repository.update', $run), ['repository' => $directory])
            ->assertRedirect(route('development-runs.show', $run));

        $prompt = $run->artifacts()->where('type', 'execution_prompt')->firstOrFail();
        $this->assertStringContainsString("Repositorio objetivo: {$directory}", $prompt->body);
        $this->assertStringNotContainsString('Repositorio objetivo: checkout-demo', $prompt->body);
    }

    public function test_repository_path_update_rejects_invalid_directories(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'repository' => 'checkout-demo', 'started_at' => now()]);

        $this->patch(route('development-runs.repository.update', $run), ['repository' => 'not-a-real-local-path'])
            ->assertRedirect(route('development-runs.show', $run))
            ->assertSessionHasErrors('repository');

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'repository' => 'checkout-demo']);
    }

    public function test_show_offers_to_define_slices_only_after_the_technical_brief_exists(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'planning',
            'active_stage' => 'plan',
            'started_at' => now(),
        ]);

        $this->get(route('development-runs.show', $run))
            ->assertDontSee('Definir slices');

        $run->artifacts()->create([
            'type' => 'technical_brief',
            'title' => 'Brief técnico inicial',
            'body' => 'El brief ya fue generado.',
            'created_by' => 'system',
        ]);

        $this->get(route('development-runs.show', $run))
            ->assertSee('Definir slices')
            ->assertSee('Contexto')
            ->assertSee('Slices');

        $run->artifacts()->create([
            'type' => 'implementation_slices',
            'title' => 'Slices de implementación',
            'body' => 'Slices ya definidos.',
            'created_by' => 'system',
        ]);

        $this->get(route('development-runs.show', $run))
            ->assertSee('Definir slices');
    }

    public function test_generating_implementation_slices_starts_slices_in_background(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'planning',
            'active_stage' => 'plan',
            'started_at' => now(),
        ]);
        $run->artifacts()->create([
            'type' => 'technical_brief',
            'title' => 'Brief técnico inicial',
            'body' => 'El brief ya fue generado.',
            'created_by' => 'system',
        ]);

        $this->post(route('development-runs.implementation-slices.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'slices_background_run',
            'title' => 'Slices en ejecución',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'slices_running', 'active_stage' => 'slices']);
        $background = $run->artifacts()->where('type', 'slices_background_run')->firstOrFail();
        $this->assertSame(2222, $background->metadata['pid']);
        $this->get(route('development-runs.show', $run))
            ->assertSee('Slices está corriendo en background')
            ->assertDontSee('Preparar Build')
            ->assertSee('Volver')
            ->assertDontSee('Volver al plan');
    }

    public function test_generating_implementation_slices_uses_the_opencode_slices_agent_when_available(): void
    {
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runSlicing(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'completed', 'exit_code' => 0, 'output' => "Slice 1 — Desde OpenCode Slices\nObjetivo: implementar el primer corte seguro."];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => base_path(),
            'status' => 'planning',
            'active_stage' => 'plan',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'technical_brief', 'title' => 'Brief técnico inicial', 'body' => 'Brief listo.', 'created_by' => 'opencode']);

        $this->post(route('development-runs.implementation-slices.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->artisan('development-run:execute-slices', ['run' => $run->id])
            ->assertSuccessful();

        $slices = $run->artifacts()->where('type', 'implementation_slices')->firstOrFail();
        $this->assertSame('opencode', $slices->created_by);
        $this->assertSame('opencode', $slices->metadata['generator']);
        $this->assertFalse($slices->metadata['fallback']);
        $this->assertSame('slices', $slices->metadata['stage_agent']);
        $this->assertStringContainsString('Desde OpenCode Slices', $slices->body);
    }

    public function test_generating_implementation_slices_falls_back_when_the_slices_agent_fails(): void
    {
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runSlicing(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'failed', 'exit_code' => 1, 'output' => 'Slices agent failed.'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'planning',
            'active_stage' => 'plan',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'technical_brief', 'title' => 'Brief técnico inicial', 'body' => 'Brief listo.', 'created_by' => 'system']);

        $this->post(route('development-runs.implementation-slices.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->artisan('development-run:execute-slices', ['run' => $run->id])
            ->assertSuccessful();

        $slices = $run->artifacts()->where('type', 'implementation_slices')->firstOrFail();
        $this->assertSame('system', $slices->created_by);
        $this->assertSame('deterministic', $slices->metadata['generator']);
        $this->assertTrue($slices->metadata['fallback']);
        $this->assertSame('opencode_failed', $slices->metadata['fallback_reason']);
        $this->assertSame(1, $slices->metadata['exit_code']);
        $this->assertStringContainsString('Slice 1', $slices->body);
    }

    public function test_preparing_build_persists_a_plan_and_advances_the_run_to_build(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'slicing',
            'active_stage' => 'slices',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'implementation_slices', 'title' => 'Slices de implementación', 'body' => "Slice 1 — Ubicar sección\nObjetivo: solo lectura.\n\nSlice 2 — Agregar aclaración compacta\nObjetivo: editar README con la explicación mínima."]);

        $this->post(route('development-runs.build-plan.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'build_plan',
            'title' => 'Plan de build inicial',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'ready_for_build', 'active_stage' => 'build']);
        $buildPlan = $run->artifacts()->where('type', 'build_plan')->firstOrFail();
        $this->assertStringContainsString('Slice 2 — Agregar aclaración compacta', $buildPlan->body);
        $this->assertStringContainsString('editar README', $buildPlan->body);
        $this->assertStringNotContainsString('modo read-only', $buildPlan->body);
        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('Build')
            ->assertSee('Plan de build inicial')
            ->assertSee('Volver')
            ->assertSee('Preparar prompt');
    }

    public function test_preparing_execution_prompt_persists_it_without_running_opencode(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => 'personal-dev-orchestrator',
            'project' => 'Command Flow',
            'status' => 'ready_for_build',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'build_plan', 'title' => 'Plan de build inicial', 'body' => 'El plan ya fue generado.']);

        $this->post(route('development-runs.execution-prompt.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'execution_prompt',
            'title' => 'Prompt de ejecución OpenCode',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'ready_for_execution', 'active_stage' => 'build']);
        $prompt = $run->artifacts()->where('type', 'execution_prompt')->firstOrFail();
        $this->assertStringContainsString('EJECUCIÓN NO INTERACTIVA', $prompt->body);
        $this->assertStringContainsString('Coordinador del Development Run: gentle-orchestrator', $prompt->body);
        $this->assertStringContainsString('Worker de esta etapa Build: build', $prompt->body);
        $this->assertStringContainsString('Este prompt está dirigido al worker de Build, no al orquestador.', $prompt->body);
        $this->assertStringContainsString("No respondas '¿En qué puedo ayudarte?'", $prompt->body);
        $this->assertStringContainsString("No respondas 'Dame el comando'", $prompt->body);
        $this->assertStringContainsString('Plan de build seleccionado', $prompt->body);
        $this->assertStringContainsString('Informar archivos modificados', $prompt->body);
        $this->assertStringContainsString('No commitear, stagear, pushear ni cambiar remotos', $prompt->body);
        $this->assertStringContainsString('Formato de respuesta obligatorio', $prompt->body);
        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('Prompt de ejecución OpenCode')
            ->assertSee('EJECUCIÓN NO INTERACTIVA')
            ->assertSee('todavía no se ejecutó OpenCode')
            ->assertSee('Volver')
            ->assertSee('Ejecutar Build');
    }

    public function test_starting_opencode_marks_build_as_running_without_blocking_the_request(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'opencode-run-'.uniqid();
        mkdir($directory);
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function startBuild(DevelopmentRun $run): ?int
            {
                return 1234;
            }

            public function startQa(DevelopmentRun $run): ?int
            {
                return null;
            }

            public function isRunning(?int $pid): bool
            {
                return $pid === 1234;
            }

            public function lastStartMetadata(): array
            {
                return ['log_path' => 'storage/logs/build.log', 'error_log_path' => 'storage/logs/build.err.log', 'php_executable' => 'php.exe'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'ready_for_execution',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Ejecutar con cuidado.']);

        $this->post(route('development-runs.opencode-execution.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'build_background_run',
            'title' => 'Build en ejecución',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'build_running', 'active_stage' => 'build']);
        $background = $run->artifacts()->where('type', 'build_background_run')->firstOrFail();
        $this->assertSame(1234, $background->metadata['pid']);
        $this->assertSame('storage/logs/build.log', $background->metadata['log_path']);
        $this->assertSame('storage/logs/build.err.log', $background->metadata['error_log_path']);
        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('Build está corriendo en background')
            ->assertSee('PID: 1234')
            ->assertSee('Log: storage/logs/build.log')
            ->assertSee('Cancelar ejecución')
            ->assertDontSee('Ejecutar Build');
    }

    public function test_build_background_command_persists_execution_result_without_git_actions(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'opencode-command-run-'.uniqid();
        mkdir($directory);
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function run(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'completed', 'exit_code' => 0, 'output' => 'OpenCode completed test run.'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'build_running',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Ejecutar con cuidado.']);
        $run->artifacts()->create(['type' => 'build_background_run', 'title' => 'Build en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 1234, 'status' => 'running']]);

        $this->artisan('development-run:execute-build', ['run' => $run->id])->assertExitCode(0);

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'opencode_execution',
            'title' => 'Ejecución OpenCode completada',
            'created_by' => 'opencode',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'build_executed', 'active_stage' => 'qa']);
        $execution = $run->artifacts()->where('type', 'opencode_execution')->firstOrFail();
        $this->assertSame('gentle-orchestrator', $execution->metadata['orchestrator_agent']);
        $this->assertSame('build', $execution->metadata['stage_agent']);
        $this->assertStringContainsString('Worker Build: build', $execution->body);
        $background = $run->artifacts()->where('type', 'build_background_run')->firstOrFail();
        $this->assertSame('Build completado', $background->title);
        $this->assertSame('completed', $background->metadata['status']);
        $this->assertArrayHasKey('finished_at', $background->metadata);
    }

    public function test_running_opencode_requires_a_valid_local_repository_path(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => 'not-a-real-local-path',
            'status' => 'ready_for_execution',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Ejecutar con cuidado.']);

        $this->post(route('development-runs.opencode-execution.store', $run))
            ->assertRedirect(route('development-runs.show', $run))
            ->assertSessionHasErrors('opencode_execution');

        $this->assertSame(0, $run->artifacts()->where('type', 'opencode_execution')->count());
    }

    public function test_build_background_command_records_a_blocked_result_when_cli_is_unavailable(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'opencode-blocked-'.uniqid();
        mkdir($directory);
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'build_running',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Ejecutar con cuidado.']);
        $run->artifacts()->create(['type' => 'build_background_run', 'title' => 'Build en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 1234, 'status' => 'running']]);

        $this->artisan('development-run:execute-build', ['run' => $run->id])->assertExitCode(0);

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'opencode_execution',
            'title' => 'Ejecución OpenCode bloqueada',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'execution_blocked', 'active_stage' => 'build']);
        $background = $run->artifacts()->where('type', 'build_background_run')->firstOrFail();
        $this->assertSame('Build bloqueado', $background->title);
        $this->assertSame('blocked', $background->metadata['status']);
    }

    public function test_starting_opencode_twice_does_not_create_duplicate_background_artifacts(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'opencode-idempotent-'.uniqid();
        mkdir($directory);
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function startBuild(DevelopmentRun $run): ?int
            {
                return 1234;
            }

            public function startQa(DevelopmentRun $run): ?int
            {
                return null;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'ready_for_execution',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'execution_prompt', 'title' => 'Prompt de ejecución OpenCode', 'body' => 'Ejecutar con cuidado.']);

        $this->post(route('development-runs.opencode-execution.store', $run));
        $this->post(route('development-runs.opencode-execution.store', $run));

        $this->assertSame(1, $run->artifacts()->where('type', 'build_background_run')->count());
        $this->assertSame(0, $run->artifacts()->where('type', 'opencode_execution')->count());
    }

    public function test_starting_qa_marks_qa_as_running_without_blocking_the_request(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qa-run-'.uniqid();
        mkdir($directory);
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function startBuild(DevelopmentRun $run): ?int
            {
                return null;
            }

            public function startQa(DevelopmentRun $run): ?int
            {
                return 5678;
            }

            public function isRunning(?int $pid): bool
            {
                return $pid === 5678;
            }

            public function lastStartMetadata(): array
            {
                return ['log_path' => 'storage/logs/qa.log', 'error_log_path' => 'storage/logs/qa.err.log', 'php_executable' => 'php.exe'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'build_executed',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 5678, 'status' => 'running']]);

        $this->post(route('development-runs.qa.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'qa_background_run',
            'title' => 'QA en ejecución',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'qa_running', 'active_stage' => 'qa']);
        $background = $run->artifacts()->where('type', 'qa_background_run')->firstOrFail();
        $this->assertSame(5678, $background->metadata['pid']);
        $this->assertSame('storage/logs/qa.log', $background->metadata['log_path']);
        $this->assertSame('storage/logs/qa.err.log', $background->metadata['error_log_path']);
        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('QA está corriendo en background')
            ->assertSee('PID: 5678')
            ->assertSee('Log: storage/logs/qa.log')
            ->assertSee('Cancelar ejecución')
            ->assertDontSee('Ejecutar QA');
    }

    public function test_cancelling_a_running_build_updates_the_run_and_background_artifact(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function cancel(DevelopmentRun $run): bool
            {
                return true;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => 'personal-dev-orchestrator',
            'status' => 'build_running',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'build_background_run', 'title' => 'Build en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 1234, 'status' => 'running']]);

        $this->post(route('development-runs.execution.cancel', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'build_cancelled', 'active_stage' => 'build']);
        $artifact = $run->artifacts()->where('type', 'build_background_run')->firstOrFail();
        $this->assertSame('Build cancelado', $artifact->title);
        $this->assertSame('cancelled', $artifact->metadata['status']);
        $this->assertTrue($artifact->metadata['cancel_signal_sent']);
    }

    public function test_cancelling_a_running_qa_updates_the_run_and_background_artifact(): void
    {
        app()->instance(DevelopmentRunBackgroundProcess::class, new class extends DevelopmentRunBackgroundProcess
        {
            public function cancel(DevelopmentRun $run): bool
            {
                return true;
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => 'personal-dev-orchestrator',
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 5678, 'status' => 'running']]);

        $this->post(route('development-runs.execution.cancel', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'qa_cancelled', 'active_stage' => 'qa']);
        $artifact = $run->artifacts()->where('type', 'qa_background_run')->firstOrFail();
        $this->assertSame('QA cancelado', $artifact->title);
        $this->assertSame('cancelled', $artifact->metadata['status']);
        $this->assertTrue($artifact->metadata['cancel_signal_sent']);
    }

    public function test_qa_background_command_persists_report_and_advances_to_review(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qa-command-run-'.uniqid();
        mkdir($directory);
        app()->instance(QaExecutionRunner::class, new class extends QaExecutionRunner
        {
            public function run(string $workingDirectory): array
            {
                return ['status' => 'passed', 'exit_code' => 0, 'command' => 'php artisan test', 'output' => 'Tests passed.'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 5678, 'status' => 'running']]);

        $this->artisan('development-run:execute-qa', ['run' => $run->id])->assertExitCode(0);

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'qa_report',
            'title' => 'QA aprobado',
            'created_by' => 'qa-agent',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'qa_passed', 'active_stage' => 'review']);
        $background = $run->artifacts()->where('type', 'qa_background_run')->firstOrFail();
        $this->assertSame('QA completado', $background->title);
        $this->assertSame('completed', $background->metadata['status']);
        $this->assertArrayHasKey('finished_at', $background->metadata);
        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('QA aprobado')
            ->assertSee('Revisión')
            ->assertSee('Cerrar run');
    }

    public function test_qa_background_command_uses_the_opencode_qa_agent_when_available(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qa-agent-command-run-'.uniqid();
        mkdir($directory);
        app()->instance(QaExecutionRunner::class, new class extends QaExecutionRunner
        {
            public function run(string $workingDirectory): array
            {
                return ['status' => 'passed', 'exit_code' => 0, 'command' => 'php artisan test', 'output' => 'Tests passed.'];
            }
        });
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runQaAnalysis(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'completed', 'exit_code' => 0, 'output' => "Resultado QA\n- Reporte desde OpenCode QA\n\nDiagnóstico\n- Evidencia interpretada."];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 5678, 'status' => 'running']]);

        $this->artisan('development-run:execute-qa', ['run' => $run->id])->assertExitCode(0);

        $report = $run->artifacts()->where('type', 'qa_report')->firstOrFail();
        $this->assertSame('opencode', $report->created_by);
        $this->assertSame('opencode', $report->metadata['generator']);
        $this->assertFalse($report->metadata['fallback']);
        $this->assertSame('qa', $report->metadata['stage_agent']);
        $this->assertSame('passed', $report->metadata['status']);
        $this->assertStringContainsString('Reporte desde OpenCode QA', $report->body);
    }

    public function test_qa_background_command_falls_back_when_the_opencode_qa_agent_fails(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'qa-agent-fallback-run-'.uniqid();
        mkdir($directory);
        app()->instance(QaExecutionRunner::class, new class extends QaExecutionRunner
        {
            public function run(string $workingDirectory): array
            {
                return ['status' => 'failed', 'exit_code' => 1, 'command' => 'php artisan test', 'output' => 'Tests failed.'];
            }
        });
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runQaAnalysis(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'failed', 'exit_code' => 1, 'output' => 'QA agent failed.'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => $directory,
            'status' => 'qa_running',
            'active_stage' => 'qa',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_background_run', 'title' => 'QA en ejecución', 'body' => 'Corriendo.', 'metadata' => ['pid' => 5678, 'status' => 'running']]);

        $this->artisan('development-run:execute-qa', ['run' => $run->id])->assertExitCode(0);

        $report = $run->artifacts()->where('type', 'qa_report')->firstOrFail();
        $this->assertSame('qa-agent', $report->created_by);
        $this->assertSame('deterministic', $report->metadata['generator']);
        $this->assertTrue($report->metadata['fallback']);
        $this->assertSame('opencode_failed', $report->metadata['fallback_reason']);
        $this->assertSame('failed', $report->metadata['status']);
        $this->assertStringContainsString('Resultado QA', $report->body);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'qa_failed', 'active_stage' => 'qa']);
    }

    public function test_review_starts_in_background_after_qa(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => 'personal-dev-orchestrator',
            'status' => 'qa_passed',
            'active_stage' => 'review',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'context', 'title' => 'Contexto inicial', 'body' => 'Contexto.']);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_report', 'title' => 'QA aprobado', 'body' => 'Tests passed.']);

        $this->post(route('development-runs.review.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'review_background_run',
            'title' => 'Revisión en ejecución',
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'review_running', 'active_stage' => 'review']);
        $background = $run->artifacts()->where('type', 'review_background_run')->firstOrFail();
        $this->assertSame(5555, $background->metadata['pid']);
        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('Revisión está corriendo en background')
            ->assertDontSee(route('development-runs.review.store', $run), false);
    }

    public function test_review_uses_the_opencode_review_agent_when_available(): void
    {
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runReview(string $workingDirectory, string $prompt): array
            {
                if (str_contains($prompt, 'review_background_run') || str_contains($prompt, 'Revisión en ejecución')) {
                    throw new \RuntimeException('Review prompt must not include the running review background artifact.');
                }

                return ['status' => 'completed', 'exit_code' => 0, 'output' => "Cierre del Development Run\n- Cierre desde OpenCode Review\n\nHandoff humano\n- Revisar y decidir."];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'repository' => base_path(),
            'status' => 'qa_passed',
            'active_stage' => 'review',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'context', 'title' => 'Contexto inicial', 'body' => 'Contexto.']);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_report', 'title' => 'QA aprobado', 'body' => 'Tests passed.']);

        $this->post(route('development-runs.review.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->artisan('development-run:execute-review', ['run' => $run->id])
            ->assertSuccessful();

        $review = $run->artifacts()->where('type', 'review_report')->firstOrFail();
        $this->assertSame('opencode', $review->created_by);
        $this->assertSame('opencode', $review->metadata['generator']);
        $this->assertFalse($review->metadata['fallback']);
        $this->assertSame('review', $review->metadata['stage_agent']);
        $this->assertStringContainsString('Cierre desde OpenCode Review', $review->body);
        $this->assertNotNull($run->fresh()->completed_at);
    }

    public function test_review_falls_back_when_the_review_agent_fails(): void
    {
        app()->instance(OpenCodeExecutionRunner::class, new class extends OpenCodeExecutionRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function runReview(string $workingDirectory, string $prompt): array
            {
                return ['status' => 'failed', 'exit_code' => 1, 'output' => 'Review agent failed.'];
            }
        });
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'qa_passed',
            'active_stage' => 'review',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'context', 'title' => 'Contexto inicial', 'body' => 'Contexto.']);
        $run->artifacts()->create(['type' => 'opencode_execution', 'title' => 'Ejecución OpenCode completada', 'body' => 'Build listo.']);
        $run->artifacts()->create(['type' => 'qa_report', 'title' => 'QA aprobado', 'body' => 'Tests passed.']);

        $this->post(route('development-runs.review.store', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->artisan('development-run:execute-review', ['run' => $run->id])
            ->assertSuccessful();

        $review = $run->artifacts()->where('type', 'review_report')->firstOrFail();
        $this->assertSame('review-agent', $review->created_by);
        $this->assertSame('deterministic', $review->metadata['generator']);
        $this->assertTrue($review->metadata['fallback']);
        $this->assertSame('opencode_failed', $review->metadata['fallback_reason']);
        $this->assertSame(1, $review->metadata['exit_code']);
        $this->assertStringContainsString('Cierre del Development Run', $review->body);
        $this->assertStringNotContainsString('review_background_run', $review->body);
        $this->assertStringNotContainsString('Revisión en ejecución', $review->body);
        $this->assertNotNull($run->fresh()->completed_at);
    }

    public function test_preparing_execution_prompt_without_a_build_plan_is_safe(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'started_at' => now()]);

        $this->post(route('development-runs.execution-prompt.store', $run))
            ->assertRedirect(route('development-runs.show', $run))
            ->assertSessionHasErrors('execution_prompt');

        $this->assertSame(0, $run->artifacts()->where('type', 'execution_prompt')->count());
    }

    public function test_preparing_execution_prompt_twice_does_not_create_duplicates(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'ready_for_build',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'build_plan', 'title' => 'Plan de build inicial', 'body' => 'El plan ya fue generado.']);

        $this->post(route('development-runs.execution-prompt.store', $run));
        $this->post(route('development-runs.execution-prompt.store', $run));

        $this->assertSame(1, $run->artifacts()->where('type', 'execution_prompt')->count());
        $this->assertStringContainsString('EJECUCIÓN NO INTERACTIVA', $run->artifacts()->where('type', 'execution_prompt')->firstOrFail()->body);
    }

    public function test_preparing_build_without_implementation_slices_is_safe(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'started_at' => now()]);

        $this->post(route('development-runs.build-plan.store', $run))
            ->assertRedirect(route('development-runs.show', $run))
            ->assertSessionHasErrors('build_plan');

        $this->assertSame(0, $run->artifacts()->where('type', 'build_plan')->count());
    }

    public function test_preparing_build_twice_does_not_create_duplicate_plans(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'slicing',
            'active_stage' => 'slices',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'implementation_slices', 'title' => 'Slices de implementación', 'body' => 'Slice 1 — Preparar cambio mínimo']);

        $this->post(route('development-runs.build-plan.store', $run));
        $this->post(route('development-runs.build-plan.store', $run));

        $this->assertSame(1, $run->artifacts()->where('type', 'build_plan')->count());
    }

    public function test_returning_to_slices_preserves_the_build_plan(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'ready_for_build',
            'active_stage' => 'build',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'implementation_slices', 'title' => 'Slices de implementación', 'body' => 'Slice 1 — Preparar cambio mínimo']);
        $run->artifacts()->create(['type' => 'build_plan', 'title' => 'Plan de build inicial', 'body' => 'El plan ya fue generado.']);

        $this->post(route('development-runs.slices.return', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'slicing', 'active_stage' => 'slices']);
        $this->assertDatabaseHas('development_run_artifacts', ['development_run_id' => $run->id, 'type' => 'build_plan', 'body' => 'El plan ya fue generado.']);
    }

    public function test_generating_implementation_slices_without_a_technical_brief_is_safe(): void
    {
        $run = DevelopmentRun::create(['title' => 'Resolver acceso', 'initial_context' => 'Validar el permiso pendiente.', 'started_at' => now()]);

        $this->post(route('development-runs.implementation-slices.store', $run))
            ->assertRedirect(route('development-runs.show', $run))
            ->assertSessionHasErrors('implementation_slices');

        $this->assertSame(0, $run->artifacts()->where('type', 'implementation_slices')->count());
    }

    public function test_generating_implementation_slices_twice_does_not_create_duplicates(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'planning',
            'active_stage' => 'plan',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'technical_brief', 'title' => 'Brief técnico inicial', 'body' => 'El brief ya fue generado.']);

        $this->post(route('development-runs.implementation-slices.store', $run));
        $this->artisan('development-run:execute-slices', ['run' => $run->id])->assertSuccessful();
        $this->post(route('development-runs.implementation-slices.store', $run));

        $this->assertSame(1, $run->artifacts()->where('type', 'implementation_slices')->count());
    }

    public function test_returning_to_plan_preserves_implementation_slices(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'slicing',
            'active_stage' => 'slices',
            'started_at' => now(),
        ]);
        $run->artifacts()->create(['type' => 'technical_brief', 'title' => 'Brief técnico inicial', 'body' => 'El brief ya fue generado.']);
        $run->artifacts()->create(['type' => 'implementation_slices', 'title' => 'Slices de implementación', 'body' => 'Slices ya definidos.']);

        $this->post(route('development-runs.plan.return', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'planning', 'active_stage' => 'plan']);
        $this->assertDatabaseHas('development_run_artifacts', ['development_run_id' => $run->id, 'type' => 'implementation_slices', 'body' => 'Slices ya definidos.']);

        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('Definir slices')
            ->assertSee('Volver');

        $this->post(route('development-runs.implementation-slices.store', $run->fresh()))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'slicing', 'active_stage' => 'slices']);
        $this->assertSame(1, $run->artifacts()->where('type', 'implementation_slices')->count());
    }

    public function test_returning_to_context_preserves_the_technical_brief(): void
    {
        $run = DevelopmentRun::create([
            'title' => 'Resolver acceso',
            'initial_context' => 'Validar el permiso pendiente.',
            'status' => 'planning',
            'active_stage' => 'plan',
            'started_at' => now(),
        ]);
        $run->artifacts()->create([
            'type' => 'technical_brief',
            'title' => 'Brief técnico inicial',
            'body' => 'El brief ya fue generado.',
            'created_by' => 'system',
        ]);

        $this->post(route('development-runs.context.return', $run))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'intake', 'active_stage' => 'contexto']);
        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'technical_brief',
            'body' => 'El brief ya fue generado.',
        ]);

        $this->get(route('development-runs.show', $run->fresh()))
            ->assertSee('Volver a Plan')
            ->assertSee('Volver')
            ->assertDontSee('Continuar al plan')
            ->assertDontSee('Comenzar');

        $this->post(route('development-runs.technical-brief.store', $run->fresh()))
            ->assertRedirect(route('development-runs.show', $run));

        $this->assertDatabaseHas('development_runs', ['id' => $run->id, 'status' => 'planning', 'active_stage' => 'plan']);
        $this->assertSame(1, $run->artifacts()->where('type', 'technical_brief')->count());
    }

    public function test_home_links_to_create_when_empty_and_to_the_active_run_when_present(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('No hay Development Runs activos.')
            ->assertSee(route('development-runs.create'));

        $run = DevelopmentRun::create(['title' => 'Continuar onboarding', 'initial_context' => 'Retomar el pedido.', 'started_at' => now()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Run activo:')
            ->assertSee('Continuar onboarding')
            ->assertSee(route('development-runs.show', $run));
    }
}
