<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorTaskVerifyTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-verify-test-'.uniqid();
        File::ensureDirectoryExists($this->repo);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repo);

        parent::tearDown();
    }

    public function test_it_runs_configured_commands_in_the_worktree_and_persists_results(): void
    {
        Storage::fake('local');
        $task = $this->task([
            'test_command' => 'Write-Output "tests passed"',
            'lint_command' => 'Write-Output "lint passed"',
        ], $this->repo);

        $this->artisan('orchestrator:task-verify', ['task' => $task->id])
            ->expectsOutputToContain('Verification passed.')
            ->assertSuccessful();

        $path = "orchestrator/tasks/{$task->id}/verification.md";
        Storage::disk('local')->assertExists($path);
        $verification = Storage::disk('local')->get($path);
        $this->assertStringContainsString('Status: passed', $verification);
        $this->assertStringContainsString('Directory: '.$this->repo, $verification);
        $this->assertStringContainsString('Source: Task worktree.', $verification);
        $this->assertStringContainsString('tests passed', $verification);
        $this->assertStringContainsString('lint passed', $verification);

        $task->refresh();
        $this->assertSame('passed', $task->last_verification_status);
        $this->assertNotNull($task->last_verified_at);
        $this->assertNotNull($task->last_verification_path);
    }

    public function test_it_records_failed_and_skipped_verifications(): void
    {
        Storage::fake('local');
        $failed = $this->task(['test_command' => 'Write-Error "tests failed"; exit 1']);

        $this->artisan('orchestrator:task-verify', ['task' => $failed->id, '--test' => true])
            ->expectsOutputToContain('Verification failed.')
            ->assertFailed();

        $failed->refresh();
        $this->assertSame('failed', $failed->last_verification_status);
        $this->assertStringContainsString('tests failed', Storage::disk('local')->get("orchestrator/tasks/{$failed->id}/verification.md"));

        $failed->project->update(['test_command' => null]);
        $skipped = OrchestratorTask::create([
            'project_id' => $failed->project_id,
            'title' => 'Skip verification',
        ]);
        $this->artisan('orchestrator:task-verify', ['task' => $skipped->id])
            ->expectsOutputToContain('No configured test or lint command matched the selected options.')
            ->assertFailed();

        $skipped->refresh();
        $this->assertSame('skipped', $skipped->last_verification_status);
        $this->assertStringContainsString('Project repository (task worktree unavailable).', Storage::disk('local')->get("orchestrator/tasks/{$skipped->id}/verification.md"));
    }

    /** @param array{test_command?: string, lint_command?: string} $commands */
    private function task(array $commands = [], ?string $worktree = null): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $this->repo,
            'default_branch' => 'main',
            ...$commands,
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Verify task',
            'worktree_path' => $worktree,
        ]);
    }
}
