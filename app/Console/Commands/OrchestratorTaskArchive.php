<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\TaskArchiver;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorTaskArchive extends Command
{
    protected $signature = 'orchestrator:task-archive {task : Task ID} {--remove-worktree : Remove the task worktree after archive artifacts are saved}';

    protected $description = 'Preserve task history and optionally remove its Git worktree';

    public function handle(TaskArchiver $archiver): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        try {
            $path = $archiver->archive($task);
        } catch (Throwable $exception) {
            $this->error("Archive failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Archive artifact: {$path}");

        if (! $this->option('remove-worktree')) {
            return self::SUCCESS;
        }

        try {
            $archiver->removeWorktree($task->refresh()->load('project'));
        } catch (Throwable $exception) {
            $this->error("Archive was saved, but the worktree was not removed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Worktree removed after archive artifacts were saved.');

        return self::SUCCESS;
    }
}
