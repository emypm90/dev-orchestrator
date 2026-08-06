<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class WorktreeService
{
    public function prepare(OrchestratorTask $task): OrchestratorTask
    {
        $project = $task->project;
        $this->validateRepository($project);

        if ($task->worktree_path !== null && is_dir($task->worktree_path)) {
            throw new RuntimeException("Task {$task->id} already has a worktree at {$task->worktree_path}.");
        }

        $branch = 'ai/task-'.$task->id.'-'.Str::slug($task->title);
        $repoPath = realpath($project->repo_path);
        $worktreePath = dirname($repoPath).DIRECTORY_SEPARATOR.basename($repoPath).'-task-'.$task->id;

        if (file_exists($worktreePath)) {
            throw new RuntimeException("Worktree path already exists: {$worktreePath}");
        }

        $this->run(['git', '-C', $repoPath, 'show-ref', '--verify', '--quiet', 'refs/heads/'.$project->default_branch]);
        $this->run(['git', '-C', $repoPath, 'worktree', 'add', '-b', $branch, $worktreePath, $project->default_branch]);

        $task->update([
            'branch_name' => $branch,
            'worktree_path' => $worktreePath,
            'status' => 'prepared',
            'prepared_at' => now(),
        ]);

        return $task->refresh();
    }

    private function validateRepository(OrchestratorProject $project): void
    {
        if (! is_dir($project->repo_path)) {
            throw new RuntimeException("Repository path does not exist: {$project->repo_path}");
        }

        $this->run(['git', '-C', $project->repo_path, 'rev-parse', '--is-inside-work-tree']);
    }

    private function run(array $command): void
    {
        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()));
        }
    }
}
