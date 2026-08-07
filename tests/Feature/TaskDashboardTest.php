<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_shows_the_task_dashboard_instead_of_the_welcome_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Centro de control')
            ->assertSee('Las decisiones de revisión se registran localmente y nunca modifican el estado de Git.')
            ->assertDontSee('Let\'s get started');
    }

    public function test_dashboard_shows_attention_rows_and_task_detail_links(): void
    {
        $project = $this->project('alpha');
        $task = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Review release notes',
            'status' => 'completed',
            'last_verification_status' => 'passed',
            'last_acceptance_status' => 'passed',
        ]);

        $this->get('/?attention=1')
            ->assertOk()
            ->assertSee('Cola de atención')
            ->assertSee('revisión humana')
            ->assertSee('Review release notes')
            ->assertSee(route('tasks.show', $task))
            ->assertSee('Revisá los artefactos; después aprobá, rechazá o pedí una revisión.');
    }

    public function test_task_detail_shows_metadata_and_acceptance_expectations(): void
    {
        $project = $this->project('beta');
        $task = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Document the dashboard',
            'status' => 'completed',
            'branch_name' => 'ai/task-1-document-dashboard',
            'worktree_path' => 'C:\\worktrees\\task-1',
            'review_decision' => 'needs_revision',
            'review_notes' => 'Add the quick start steps.',
            'last_verification_status' => 'passed',
            'last_verification_path' => 'C:\\artifacts\\verification.md',
            'last_acceptance_status' => 'failed',
            'last_acceptance_path' => 'C:\\artifacts\\acceptance.md',
            'expected_files' => ['docs/dashboard.md'],
            'forbidden_files' => ['composer.lock'],
            'expected_texts' => [['file' => 'docs/dashboard.md', 'text' => 'Read-only']],
            'expected_regexes' => [['file' => 'docs/dashboard.md', 'pattern' => '/dashboard/']],
        ]);

        $this->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Document the dashboard')
            ->assertSee('C:\\worktrees\\task-1')
            ->assertSee('Add the quick start steps.')
            ->assertSee('Requiere revisión')
            ->assertSee('Superada')
            ->assertSee('Fallida')
            ->assertSee('Archivos esperados (1)')
            ->assertSee('Archivos prohibidos (1)')
            ->assertSee('Textos esperados (1)')
            ->assertSee('Expresiones regulares esperadas (1)')
            ->assertSee('docs/dashboard.md')
            ->assertSee('Corregí la falla de aceptación y volvé a ejecutar.');
    }

    public function test_task_detail_shows_safe_review_decision_forms(): void
    {
        $task = OrchestratorTask::create([
            'project_id' => $this->project('review-forms')->id,
            'title' => 'Review web forms',
            'status' => 'completed',
        ]);

        $this->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Registrar una decisión de revisión')
            ->assertSee('Estas acciones solo registran una decisión humana. No ejecutan, archivan ni modifican el estado de Git.')
            ->assertSee(route('tasks.approve', $task))
            ->assertSee(route('tasks.revision', $task))
            ->assertSee(route('tasks.reject', $task))
            ->assertSee('name="notes"', false)
            ->assertSee('name="reason"', false);
    }

    public function test_task_detail_links_to_available_artifacts_only(): void
    {
        Storage::fake('local');
        $task = OrchestratorTask::create([
            'project_id' => $this->project('artifacts')->id,
            'title' => 'Inspect artifacts',
            'status' => 'completed',
        ]);
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/prompt.md", '# Prompt');
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/revision-2.md", '# Revision');

        $this->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee(route('tasks.artifacts.show', ['task' => $task, 'artifact' => 'prompt.md']))
            ->assertSee(route('tasks.artifacts.show', ['task' => $task, 'artifact' => 'revision-2.md']))
            ->assertSee('run.log (no disponible)');
    }

    public function test_allowed_artifact_renders_escaped_read_only_content(): void
    {
        Storage::fake('local');
        $task = OrchestratorTask::create([
            'project_id' => $this->project('viewer')->id,
            'title' => 'View artifact',
            'status' => 'completed',
        ]);
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/prompt.md", '<script>alert("unsafe")</script>');

        $this->get(route('tasks.artifacts.show', ['task' => $task, 'artifact' => 'prompt.md']))
            ->assertOk()
            ->assertSee('CONTENIDO DE SOLO LECTURA')
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false);
    }

    public function test_unknown_or_nested_artifacts_return_not_found(): void
    {
        $task = OrchestratorTask::create([
            'project_id' => $this->project('restricted')->id,
            'title' => 'Restrict artifacts',
            'status' => 'completed',
        ]);

        $this->get(route('tasks.artifacts.show', ['task' => $task, 'artifact' => 'unknown.md']))->assertNotFound();
        $this->get("/tasks/{$task->id}/artifacts/prompt.md/nested")->assertNotFound();
    }

    private function project(string $name): OrchestratorProject
    {
        return OrchestratorProject::create([
            'name' => $name,
            'repo_path' => 'C:\\workspace\\'.$name,
            'default_branch' => 'main',
        ]);
    }
}
