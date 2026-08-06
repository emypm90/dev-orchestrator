<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorTaskDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_approval_rejection_and_revision_decisions_with_artifacts(): void
    {
        Storage::fake('local');

        $cases = [
            ['orchestrator:task-approve', 'notes', 'Approved after manual checks.', 'approved'],
            ['orchestrator:task-reject', 'reason', 'Does not meet acceptance criteria.', 'rejected'],
            ['orchestrator:task-revision', 'reason', 'Create the requested documentation file.', 'needs_revision'],
        ];

        foreach ($cases as [$command, $option, $notes, $decision]) {
            $task = $this->task();
            Storage::disk('local')->put("orchestrator/tasks/{$task->id}/review.md", 'Review retained.');
            $task->update(['last_verification_status' => 'passed']);

            $this->artisan($command, ['task' => $task->id, "--{$option}" => $notes])
                ->assertSuccessful();

            $task->refresh();
            $this->assertSame($decision, $task->review_decision);
            $this->assertSame($decision, $task->status);
            $this->assertSame($notes, $task->review_notes);
            $this->assertNotNull($task->reviewed_at);
            Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/decision.md");

            $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/decision.md");
            $this->assertStringContainsString("- Decision: {$decision}", $artifact);
            $this->assertStringContainsString($notes, $artifact);
            $this->assertStringContainsString('- Latest verification status: passed', $artifact);
            $this->assertStringContainsString('review.md', $artifact);
        }
    }

    public function test_it_fails_clearly_when_approving_a_missing_task(): void
    {
        $this->artisan('orchestrator:task-approve', ['task' => 999])
            ->expectsOutputToContain('Task not found.')
            ->assertFailed();
    }

    private function task(): OrchestratorTask
    {
        $worktree = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-decision-'.uniqid();
        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $worktree,
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Human review task',
            'status' => 'completed',
            'worktree_path' => $worktree,
        ]);
    }
}
