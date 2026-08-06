<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\OpenCodeRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrchestratorTaskBatchRunTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-batch-test-'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_rejects_invalid_concurrency_duplicate_ids_and_unsafe_states(): void
    {
        $task = $this->task('prepared');

        $this->artisan('orchestrator:task-batch-run', ['tasks' => [$task->id], '--concurrency' => 5])
            ->expectsOutputToContain('Concurrency must be an integer between 1 and 4.')
            ->assertFailed();

        $this->artisan('orchestrator:task-batch-run', ['tasks' => [$task->id, $task->id]])
            ->expectsOutputToContain('Each task ID may be provided only once per batch.')
            ->assertFailed();

        $draft = $this->task('draft');
        $this->artisan('orchestrator:task-batch-run', ['tasks' => [$draft->id]])
            ->expectsOutputToContain("Task {$draft->id} is draft. Use --prepare")
            ->assertFailed();

        $running = $this->task('running');
        $this->artisan('orchestrator:task-batch-run', ['tasks' => [$running->id]])
            ->expectsOutputToContain("Task {$running->id} is running and cannot be included in a batch.")
            ->assertFailed();
    }

    public function test_it_writes_a_batch_artifact_when_opencode_is_unavailable(): void
    {
        Storage::fake('local');
        $first = $this->task('prepared');
        $second = $this->task('failed');
        app()->instance(OpenCodeRunner::class, new class extends OpenCodeRunner
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->artisan('orchestrator:task-batch-run', [
            'tasks' => [$first->id, $second->id],
            '--concurrency' => 2,
        ])->expectsOutputToContain('Batch completed with blocked or failed tasks.')
            ->assertFailed();

        $files = Storage::disk('local')->allFiles('orchestrator/batches');
        $this->assertCount(1, $files);
        $batch = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('- Task IDs: '.$first->id.', '.$second->id, $batch);
        $this->assertStringContainsString('- Concurrency: 2', $batch);
        $this->assertStringContainsString("| {$first->id} | blocked | - |", $batch);
        $this->assertStringContainsString('Install or expose opencode', $batch);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$first->id}/run.log");
        Storage::disk('local')->assertExists("orchestrator/tasks/{$second->id}/run.log");

        $this->assertSame('blocked', $first->refresh()->status);
        $this->assertSame('blocked', $second->refresh()->status);
    }

    public function test_it_reports_overlapping_changed_files_in_the_batch_artifact(): void
    {
        Storage::fake('local');
        $first = $this->gitTask('README.md');
        $second = $this->gitTask('README.md');
        app()->instance(OpenCodeRunner::class, new class extends OpenCodeRunner
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->artisan('orchestrator:task-batch-run', [
            'tasks' => [$first->id, $second->id],
        ])->assertFailed();

        $batch = Storage::disk('local')->get(Storage::disk('local')->allFiles('orchestrator/batches')[0]);
        $this->assertStringContainsString('## Potential file conflicts', $batch);
        $this->assertStringContainsString("- `README.md`: tasks #{$first->id}, #{$second->id}", $batch);
    }

    private function task(string $status): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $this->root.DIRECTORY_SEPARATOR.uniqid(),
            'default_branch' => 'main',
        ]);
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree-'.uniqid();
        File::ensureDirectoryExists($worktree);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Batch task',
            'status' => $status,
            'worktree_path' => $status === 'draft' ? null : $worktree,
        ]);
    }

    private function gitTask(string $changedFile): OrchestratorTask
    {
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree-'.uniqid();
        File::ensureDirectoryExists($worktree);
        file_put_contents($worktree.DIRECTORY_SEPARATOR.$changedFile, "Initial\n");
        foreach ([
            ['git', 'init', '-b', 'main', $worktree],
            ['git', '-C', $worktree, 'config', 'user.email', 'test@example.com'],
            ['git', '-C', $worktree, 'config', 'user.name', 'Test User'],
            ['git', '-C', $worktree, 'add', $changedFile],
            ['git', '-C', $worktree, 'commit', '-m', 'Initial commit'],
        ] as $command) {
            (new Process($command))->mustRun();
        }
        file_put_contents($worktree.DIRECTORY_SEPARATOR.$changedFile, "Changed\n");

        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $worktree,
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Batch conflict task',
            'status' => 'prepared',
            'worktree_path' => $worktree,
        ]);
    }
}
