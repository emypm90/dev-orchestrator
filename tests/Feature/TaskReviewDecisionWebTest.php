<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskReviewDecisionWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_records_the_decision_artifact_and_redirects_to_the_task(): void
    {
        Storage::fake('local');
        $task = $this->task();

        $response = $this->post(route('tasks.approve', $task));

        $response->assertRedirect(route('tasks.show', $task));
        $response->assertSessionHas('success', "La tarea {$task->id} fue aprobada. Decisión registrada.");
        $task->refresh();
        $this->assertSame('approved', $task->status);
        $this->assertSame('approved', $task->review_decision);
        $this->assertSame('No se proporcionaron notas.', $task->review_notes);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/decision.md");

        $this->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee(route('tasks.artifacts.show', ['task' => $task, 'name' => 'decision.md']));
    }

    public function test_revision_records_the_reason(): void
    {
        Storage::fake('local');
        $task = $this->task();

        $this->post(route('tasks.revision', $task), ['reason' => 'Document the setup steps.'])
            ->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertSame('needs_revision', $task->status);
        $this->assertSame('needs_revision', $task->review_decision);
        $this->assertSame('Document the setup steps.', $task->review_notes);
    }

    public function test_reject_records_the_reason(): void
    {
        Storage::fake('local');
        $task = $this->task();

        $this->post(route('tasks.reject', $task), ['reason' => 'Does not meet acceptance criteria.'])
            ->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertSame('rejected', $task->status);
        $this->assertSame('rejected', $task->review_decision);
        $this->assertSame('Does not meet acceptance criteria.', $task->review_notes);
    }

    public function test_review_input_must_not_exceed_the_maximum_length(): void
    {
        $task = $this->task();

        $this->from(route('tasks.show', $task))
            ->post(route('tasks.revision', $task), ['reason' => str_repeat('a', 2001)])
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHasErrors([
                'reason' => 'El motivo no puede superar los 2000 caracteres.',
            ]);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNull($task->review_decision);
    }

    private function task(): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'web-review-'.uniqid(),
            'repo_path' => 'C:\\workspace\\web-review',
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Human review task',
            'status' => 'completed',
        ]);
    }
}
