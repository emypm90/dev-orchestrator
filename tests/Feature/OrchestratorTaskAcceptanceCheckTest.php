<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorTaskAcceptanceCheckTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-acceptance-test-'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_adds_a_normalized_expected_file_only_once(): void
    {
        $task = $this->task(['docs/guide.md']);

        $this->artisan('orchestrator:task-expect-file', ['task' => $task->id, 'file' => '.\\docs\\guide.md'])->assertSuccessful();
        $this->assertSame(['docs/guide.md'], $task->fresh()->expected_files);

        $this->artisan('orchestrator:task-expect-file', ['task' => $task->id, 'file' => '../outside.md'])
            ->expectsOutputToContain('cannot contain ".."')->assertFailed();

        $this->artisan('orchestrator:task-expect-file', ['task' => 999, 'file' => 'docs/missing.md'])
            ->expectsOutputToContain('Task not found.')->assertFailed();
    }

    public function test_it_records_a_passed_check_from_the_worktree(): void
    {
        Storage::fake('local');
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree';
        File::ensureDirectoryExists($worktree.DIRECTORY_SEPARATOR.'docs');
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'guide.md', "Guide\n");
        $task = $this->task(['docs/guide.md'], $worktree);

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $task->id])->assertSuccessful();

        $task->refresh();
        $this->assertSame('passed', $task->last_acceptance_status);
        $this->assertNotNull($task->last_acceptance_checked_at);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/acceptance.md");
        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/acceptance.md");
        $this->assertStringContainsString('Status: passed', $artifact);
        $this->assertStringContainsString('Directory source: task worktree', $artifact);
        $this->assertStringContainsString('`docs/guide.md`', $artifact);
    }

    public function test_it_records_failed_and_skipped_checks_using_the_project_repository_when_needed(): void
    {
        Storage::fake('local');
        $failed = $this->task(['docs/missing.md'], $this->root.DIRECTORY_SEPARATOR.'missing-worktree');

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $failed->id])->assertFailed();
        $failed->refresh();
        $this->assertSame('failed', $failed->last_acceptance_status);
        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$failed->id}/acceptance.md");
        $this->assertStringContainsString('Directory source: project repository', $artifact);
        $this->assertStringContainsString('`docs/missing.md`', $artifact);

        $skipped = $this->task(null, null);
        $this->artisan('orchestrator:task-acceptance-check', ['task' => $skipped->id])->assertFailed();
        $this->assertSame('skipped', $skipped->fresh()->last_acceptance_status);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$skipped->id}/acceptance.md");
    }

    /** @param array<int, string>|null $expectedFiles */
    private function task(?array $expectedFiles, ?string $worktree = null): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $this->root.DIRECTORY_SEPARATOR.'repo-'.uniqid(),
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Check expected files',
            'worktree_path' => $worktree,
            'expected_files' => $expectedFiles,
        ]);
    }
}
