<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\OpenCodeRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorTaskRerunTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-rerun-test-'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_refuses_tasks_that_cannot_be_rerun(): void
    {
        foreach (['approved', 'archived', 'running'] as $status) {
            $task = $this->task($status);

            $this->artisan('orchestrator:task-rerun', ['task' => $task->id])
                ->expectsOutputToContain("Task {$task->id} is {$status} and cannot be rerun.")
                ->assertFailed();
        }

        $completed = $this->task('completed');
        $this->artisan('orchestrator:task-rerun', ['task' => $completed->id])
            ->expectsOutputToContain('Completed tasks must be marked needs_revision before rerunning.')
            ->assertFailed();
    }

    public function test_it_requires_an_existing_worktree(): void
    {
        $task = $this->task('needs_revision', null);

        $this->artisan('orchestrator:task-rerun', ['task' => $task->id])
            ->expectsOutputToContain('Task must have an existing worktree before it can be rerun.')
            ->assertFailed();
    }

    public function test_it_creates_revision_artifacts_and_clears_the_stale_decision(): void
    {
        Storage::fake('local');
        $task = $this->task('needs_revision');
        $task->update([
            'review_decision' => 'needs_revision',
            'reviewed_at' => now(),
            'review_notes' => 'Create the requested docs files.',
            'last_verification_status' => 'failed',
            'expected_files' => ['docs/requested.md'],
        ]);
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/decision.md", "# Review decision\n\nCreate docs files.\n");
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/review.md", '# Review');
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/verification.md", '# Verification');
        app()->instance(OpenCodeRunner::class, new class extends OpenCodeRunner
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function run(OrchestratorTask $task, string $promptPath): int
            {
                $task->update(['status' => 'completed', 'last_exit_code' => 0, 'finished_at' => now()]);

                return 0;
            }
        });

        $this->artisan('orchestrator:task-rerun', [
            'task' => $task->id,
            '--instructions' => 'Create the requested files and preserve good work.',
        ])->assertSuccessful();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNull($task->review_decision);
        $this->assertNull($task->reviewed_at);
        $this->assertNull($task->review_notes);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/revision-1.md");
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/rerun.md");

        $prompt = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/revision-1.md");
        $this->assertStringContainsString('Create docs files.', $prompt);
        $this->assertStringContainsString('Create the requested files and preserve good work.', $prompt);
        $this->assertStringContainsString('Do not commit, stage, push', $prompt);
        $this->assertStringContainsString('`docs/requested.md`', $prompt);

        $rerun = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/rerun.md");
        $this->assertStringContainsString('## Rerun attempt 1', $rerun);
        $this->assertStringContainsString('- Exit status: 0', $rerun);
        $this->assertStringContainsString('### Previous decision', $rerun);
    }

    private function task(string $status, ?string $worktree = ''): OrchestratorTask
    {
        if ($worktree === '') {
            $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree-'.uniqid();
            File::ensureDirectoryExists($worktree);
        }
        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $this->root.DIRECTORY_SEPARATOR.'repo-'.uniqid(),
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Revise documentation output',
            'description' => 'Create the requested documentation files.',
            'acceptance_criteria' => 'The requested files exist.',
            'status' => $status,
            'worktree_path' => $worktree,
        ]);
    }
}
