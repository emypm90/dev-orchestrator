<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrchestratorTaskArchiveTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-archive-test-'.uniqid();
        $this->repo = $this->root.DIRECTORY_SEPARATOR.'source';
        File::ensureDirectoryExists($this->repo);
        file_put_contents($this->repo.DIRECTORY_SEPARATOR.'README.md', "# Source\n");

        foreach ([
            ['git', 'init', '-b', 'main', $this->repo],
            ['git', '-C', $this->repo, 'config', 'user.email', 'test@example.com'],
            ['git', '-C', $this->repo, 'config', 'user.name', 'Test User'],
            ['git', '-C', $this->repo, 'add', 'README.md'],
            ['git', '-C', $this->repo, 'commit', '-m', 'Initial commit'],
        ] as $command) {
            (new Process($command))->mustRun();
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_preserves_archive_artifacts_before_leaving_the_worktree_in_place(): void
    {
        Storage::fake('local');
        $task = $this->task($this->repo, 'completed');
        file_put_contents($this->repo.DIRECTORY_SEPARATOR.'README.md', "# Source\n\nChanged.\n");
        file_put_contents($this->repo.DIRECTORY_SEPARATOR.'NEW.md', "New file\n");
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/review.md", 'Review retained.');
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/verification.md", 'Verification retained.');
        $task->update(['last_verification_status' => 'passed']);
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/acceptance.md", 'Acceptance retained.');
        $task->update(['last_acceptance_status' => 'passed']);
        $task->update([
            'review_decision' => 'approved',
            'reviewed_at' => now(),
            'review_notes' => 'Approved after review.',
        ]);
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/decision.md", 'Decision retained.');

        $this->artisan('orchestrator:task-archive', ['task' => $task->id])->assertSuccessful();

        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/archive.md");
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/final.patch");
        $archive = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/archive.md");
        $this->assertStringContainsString('README.md', $archive);
        $this->assertStringContainsString('NEW.md', $archive);
        $this->assertStringContainsString('Review retained.', Storage::disk('local')->get("orchestrator/tasks/{$task->id}/review.md"));
        $this->assertStringContainsString('Status: passed', $archive);
        $this->assertStringContainsString('Latest verification', $archive);
        $this->assertStringContainsString('Latest acceptance check', $archive);
        $this->assertStringContainsString('Decision: approved', $archive);
        $this->assertStringContainsString('Decision retained.', Storage::disk('local')->get("orchestrator/tasks/{$task->id}/decision.md"));
        $this->assertDirectoryExists($this->repo);

        $task->refresh();
        $this->assertSame('archived', $task->status);
        $this->assertNotNull($task->archived_at);
        $this->assertNull($task->worktree_removed_at);
    }

    public function test_it_removes_a_prepared_worktree_only_after_archiving(): void
    {
        Storage::fake('local');
        $project = OrchestratorProject::create([
            'name' => 'source',
            'repo_path' => $this->repo,
            'default_branch' => 'main',
        ]);
        $task = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Remove after archive',
        ]);

        $this->artisan('orchestrator:task-prepare', ['task' => $task->id])->assertSuccessful();
        $task->refresh();
        $worktree = $task->worktree_path;

        $this->artisan('orchestrator:task-archive', [
            'task' => $task->id,
            '--remove-worktree' => true,
        ])->assertSuccessful();

        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/archive.md");
        $this->assertDirectoryDoesNotExist($worktree);

        $task->refresh();
        $this->assertSame('archived', $task->status);
        $this->assertNotNull($task->worktree_removed_at);
        $this->assertDatabaseHas('orchestrator_tasks', ['id' => $task->id]);
    }

    private function task(string $worktree, string $status): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'source',
            'repo_path' => $this->repo,
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Archive changes',
            'status' => $status,
            'worktree_path' => $worktree,
        ]);
    }
}
