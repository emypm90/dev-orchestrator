<?php

namespace Tests\Feature;

use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrchestratorTaskPrepareTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-test-'.uniqid();
        $repo = $this->root.DIRECTORY_SEPARATOR.'source';
        File::ensureDirectoryExists($repo);
        file_put_contents($repo.DIRECTORY_SEPARATOR.'README.md', '# Source');

        foreach ([
            ['git', 'init', '-b', 'main', $repo],
            ['git', '-C', $repo, 'config', 'user.email', 'test@example.com'],
            ['git', '-C', $repo, 'config', 'user.name', 'Test User'],
            ['git', '-C', $repo, 'add', 'README.md'],
            ['git', '-C', $repo, 'commit', '-m', 'Initial commit'],
        ] as $command) {
            $process = new Process($command);
            $process->mustRun();
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_it_prepares_an_isolated_worktree_and_prompt(): void
    {
        Storage::fake('local');
        $repo = $this->root.DIRECTORY_SEPARATOR.'source';

        $this->artisan('orchestrator:project-add', [
            'name' => 'source',
            'repo_path' => $repo,
            '--rules' => 'Do not change README.',
            '--test' => 'php artisan test',
        ])->assertSuccessful();
        $this->artisan('orchestrator:task-create', [
            'project' => 'source',
            'title' => 'Prepare export',
            '--acceptance' => 'Prompt has acceptance criteria.',
        ])->assertSuccessful();

        $this->artisan('orchestrator:task-prepare', ['task' => 1])->assertSuccessful();

        $task = OrchestratorTask::findOrFail(1);
        $this->assertSame('prepared', $task->status);
        $this->assertSame('ai/task-1-prepare-export', $task->branch_name);
        $this->assertDirectoryExists($task->worktree_path);
        Storage::disk('local')->assertExists('orchestrator/tasks/1/prompt.md');
    }
}
