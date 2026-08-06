<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ReviewCollector;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorTaskReview extends Command
{
    protected $signature = 'orchestrator:task-review {task : Task ID}';

    protected $description = 'Collect Git diff, modified files, and task summary artifacts';

    public function handle(ReviewCollector $reviews): int
    {
        $task = OrchestratorTask::find($this->argument('task'));
        if ($task === null || $task->worktree_path === null || ! is_dir($task->worktree_path)) {
            $this->error('Task must have an existing worktree before review.');

            return self::FAILURE;
        }

        try {
            $path = $reviews->collect($task);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Review artifact: {$path}");

        return self::SUCCESS;
    }
}
