<?php

namespace Tests\Feature;

use App\Models\DevelopmentRun;
use App\Models\OrchestratorProject;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectContextRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_project_index_and_create_page_allow_manual_project_context_entry(): void
    {
        OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Usar TDD estricto y no ejecutar Git desde la app.',
        ]);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Command Flow')
            ->assertSee('C:\\work\\command-flow')
            ->assertSee(route('projects.create'), false);

        $this->get(route('projects.create'))
            ->assertOk()
            ->assertSee('Nuevo proyecto')
            ->assertSee('Contexto inicial del proyecto')
            ->assertSee('Ruta del repositorio');
    }

    public function test_store_creates_project_without_git_mutations_and_shows_reusable_context(): void
    {
        $response = $this->post(route('projects.store'), [
            'name' => 'Personal Dev Orchestrator',
            'repo_path' => 'C:\\Users\\dev21\\Documents\\proyecto\\personal-dev-orchestrator',
            'rules' => "Respetar arquitectura Laravel.\nNo stage, commit, push ni checkout desde la app.",
        ]);

        $project = OrchestratorProject::firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('orchestrator_projects', [
            'name' => 'Personal Dev Orchestrator',
            'repo_path' => 'C:\\Users\\dev21\\Documents\\proyecto\\personal-dev-orchestrator',
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Personal Dev Orchestrator')
            ->assertSee('No stage, commit, push ni checkout desde la app')
            ->assertSee(route('projects.development-runs.create', $project), false);
    }

    public function test_edit_page_exposes_project_metadata_and_context_without_git_actions(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Contexto reusable existente.',
        ]);

        $this->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('Editar proyecto')
            ->assertSee('Command Flow')
            ->assertSee('C:\\work\\command-flow')
            ->assertSee('Contexto reusable existente.')
            ->assertSee('No ejecuta Git', false);
    }

    public function test_update_saves_project_context_without_git_mutations(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Contexto viejo.',
        ]);

        $response = $this->put(route('projects.update', $project), [
            'name' => 'Command Flow Actualizado',
            'repo_path' => 'D:\\work\\command-flow',
            'rules' => "Contexto actualizado.\nNo stage, commit, push, merge, reset, checkout ni remote desde la app.",
        ]);

        $response->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('orchestrator_projects', [
            'id' => $project->id,
            'name' => 'Command Flow Actualizado',
            'repo_path' => 'D:\\work\\command-flow',
            'rules' => "Contexto actualizado.\nNo stage, commit, push, merge, reset, checkout ni remote desde la app.",
        ]);
        $this->assertDatabaseCount('development_runs', 0);

        $this->get(route('projects.show', $project->fresh()))
            ->assertOk()
            ->assertSee('Command Flow Actualizado')
            ->assertSee('D:\\work\\command-flow')
            ->assertSee('No stage, commit, push, merge, reset, checkout ni remote desde la app');
    }

    public function test_missing_project_rejects_scoped_run_creation_without_creating_a_run(): void
    {
        $this->get('/projects/999/development-runs/create')->assertNotFound();

        $this->post('/projects/999/development-runs', [
            'title' => 'No debería crearse',
            'initial_context' => 'Este run no tiene proyecto válido.',
            'priority' => 'normal',
        ])->assertNotFound();

        $this->assertDatabaseCount('development_runs', 0);
    }

    public function test_nested_run_creation_inherits_project_repository_and_records_bounded_project_context(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Regla de proyecto: mantener Git manual y contexto reutilizable.',
        ]);

        $this->get(route('projects.development-runs.create', $project))
            ->assertOk()
            ->assertSee('Command Flow')
            ->assertSee('C:\\work\\command-flow')
            ->assertSee('Contexto específico de la tarea');

        $response = $this->post(route('projects.development-runs.store', $project), [
            'title' => 'Agregar contexto acotado',
            'initial_context' => 'Contexto de tarea: crear un primer slice chico.',
            'priority' => 'alta',
        ]);

        $run = DevelopmentRun::firstOrFail();

        $response->assertRedirect(route('development-runs.show', $run));
        $this->assertTrue($run->projectModel->is($project));
        $this->assertSame('Command Flow', $run->project);
        $this->assertSame('C:\\work\\command-flow', $run->repository);

        $this->assertDatabaseHas('development_run_artifacts', [
            'development_run_id' => $run->id,
            'type' => 'project_context',
            'title' => 'Contexto del proyecto',
        ]);

        $projectContext = $run->artifacts()->where('type', 'project_context')->firstOrFail()->body;
        $this->assertStringContainsString('[project:Command Flow]', $projectContext);
        $this->assertStringContainsString('Regla de proyecto: mantener Git manual', $projectContext);
        $this->assertStringContainsString('[run:contexto-inicial]', $projectContext);
        $this->assertStringContainsString('Contexto de tarea: crear un primer slice chico.', $projectContext);
    }
}
