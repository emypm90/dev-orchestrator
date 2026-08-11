<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\TaskDiffViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TaskDiffWebTest extends TestCase
{
    use RefreshDatabase;

    private string $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-diff-'.uniqid();
        File::makeDirectory($this->repository);
        $this->git('init');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test User');
        File::put($this->repository.DIRECTORY_SEPARATOR.'tracked.txt', "before\n");
        $this->git('add', 'tracked.txt');
        $this->git('commit', '-m', 'Initial commit');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repository);
        parent::tearDown();
    }

    public function test_missing_worktree_path_returns_a_graceful_empty_diff(): void
    {
        $task = $this->task(null);

        $result = app(TaskDiffViewer::class)->collect($task);

        $this->assertSame([], $result['files']);
        $this->assertSame('Esta tarea no tiene una ruta de worktree para inspeccionar.', $result['warning']);
        $this->get(route('tasks.diff', $task))->assertOk()->assertSee($result['warning']);
    }

    public function test_diff_route_renders_tracked_changes_from_the_task_worktree(): void
    {
        File::put($this->repository.DIRECTORY_SEPARATOR.'tracked.txt', "after\n");
        $task = $this->task($this->repository);

        $this->get(route('tasks.diff', $task))
            ->assertOk()
            ->assertSee('Archivos cambiados')
            ->assertSee('tracked.txt')
            ->assertSee('-before', false)
            ->assertSee('+after', false)
            ->assertSee('Esta vista no modifica Git.');
    }

    public function test_untracked_files_are_listed_with_their_content(): void
    {
        File::put($this->repository.DIRECTORY_SEPARATOR.'new-file.txt', "untracked content\n");
        $task = $this->task($this->repository);

        $this->get(route('tasks.diff', $task))
            ->assertOk()
            ->assertSee('new-file.txt')
            ->assertSee('untracked content');
    }

    private function task(?string $worktree): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'diff-'.uniqid(),
            'repo_path' => $this->repository,
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Inspect worktree diff',
            'status' => 'completed',
            'worktree_path' => $worktree,
        ]);
    }

    private function git(string ...$arguments): void
    {
        $process = new Process(['git', '-C', $this->repository, ...$arguments]);
        $process->mustRun();
    }
}
