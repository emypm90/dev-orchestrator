<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class OrchestratorTaskOpen extends Command
{
    protected $signature = 'orchestrator:task-open {task : Task ID}';

    protected $description = 'Open the task worktree in VS Code';

    public function handle(): int
    {
        $task = OrchestratorTask::find($this->argument('task'));
        if ($task === null || $task->worktree_path === null || ! is_dir($task->worktree_path)) {
            $this->error('Task must have an existing worktree before it can be opened.');

            return self::FAILURE;
        }

        $process = new Process(['where', 'code']);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('VS Code CLI ("code") was not found on PATH.');

            return self::FAILURE;
        }

        $process = new Process(['code', $task->worktree_path]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to open VS Code: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        $this->info("Opened worktree in VS Code: {$task->worktree_path}");

        return self::SUCCESS;
    }
}
