<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskArchiveWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_task_can_be_archived_from_the_detail_page_without_removing_its_worktree(): void
    {
        Storage::fake('local');
        $task = $this->task(['review_decision' => 'approved', 'reviewed_at' => now()]);

        $this->post(route('tasks.archive', $task))
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHas('success', "La tarea {$task->id} fue archivada. Se conservaron sus artefactos y su worktree.");

        $task->refresh();
        $this->assertSame('archived', $task->status);
        $this->assertNotNull($task->archived_at);
        $this->assertNotNull($task->archive_path);
        $this->assertNull($task->worktree_removed_at);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/archive.md");

        $this->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('La tarea fue archivada el')
            ->assertSee(route('tasks.artifacts.show', ['task' => $task, 'name' => 'archive.md']));
    }

    public function test_a_task_without_a_final_decision_cannot_be_archived(): void
    {
        $task = $this->task();

        $this->post(route('tasks.archive', $task))
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHasErrors('archive');

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNull($task->archived_at);
    }

    public function test_a_task_needing_revision_cannot_be_archived(): void
    {
        $task = $this->task(['status' => 'needs_revision', 'review_decision' => 'needs_revision']);

        $this->post(route('tasks.archive', $task))
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHasErrors('archive');

        $task->refresh();
        $this->assertSame('needs_revision', $task->status);
        $this->assertNull($task->archived_at);
    }

    public function test_the_detail_page_shows_archive_safety_copy(): void
    {
        $task = $this->task(['review_decision' => 'approved', 'reviewed_at' => now()]);

        $this->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Archivar tarea')
            ->assertSee('Archivar no elimina el worktree ni modifica el estado de Git.')
            ->assertSee(route('tasks.archive', $task))
            ->assertSee('Archivar tarea');
    }

    private function task(array $attributes = []): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'web-archive-'.uniqid(),
            'repo_path' => 'C:\\workspace\\web-archive',
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create(array_merge([
            'project_id' => $project->id,
            'title' => 'Archive web task',
            'status' => 'completed',
        ], $attributes));
    }
}
