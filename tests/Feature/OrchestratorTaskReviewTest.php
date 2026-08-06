<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrchestratorTaskReviewTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-review-test-'.uniqid();
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
        File::deleteDirectory($this->repo);

        parent::tearDown();
    }

    public function test_it_includes_untracked_files_and_fallback_summary_in_review_artifact(): void
    {
        Storage::fake('local');

        file_put_contents($this->repo.DIRECTORY_SEPARATOR.'README.md', "# Source\n\nChanged.\n");
        file_put_contents($this->repo.DIRECTORY_SEPARATOR.'NEW_COMMAND.md', "# New command\n");

        $project = OrchestratorProject::create([
            'name' => 'source',
            'repo_path' => $this->repo,
            'default_branch' => 'main',
        ]);
        $task = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Review changes',
            'status' => 'completed',
            'worktree_path' => $this->repo,
        ]);
        Storage::disk('local')->put("orchestrator/tasks/{$task->id}/verification.md", 'Verification retained.');
        $task->update(['last_verification_status' => 'passed']);

        $this->artisan('orchestrator:task-review', ['task' => $task->id])->assertSuccessful();

        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/review.md");
        $review = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/review.md");

        $this->assertStringContainsString('README.md', $review);
        $this->assertStringContainsString('NEW_COMMAND.md', $review);
        $this->assertStringContainsString('Untracked files:', $review);
        $this->assertStringContainsString('Fallback summary', $review);
        $this->assertStringContainsString('Latest verification', $review);
        $this->assertStringContainsString('Status: passed', $review);
    }
}
