<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\OpenCodeRunner;
use App\Services\Orchestrator\PromptBuilder;
use App\Services\Orchestrator\ReviewCollector;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorTaskRun extends Command
{
    protected $signature = 'orchestrator:task-run {task : Task ID}';

    protected $description = 'Run a prepared task through the local OpenCode CLI';

    public function handle(PromptBuilder $prompts, OpenCodeRunner $runner, ReviewCollector $reviews): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null || $task->worktree_path === null || ! is_dir($task->worktree_path)) {
            $this->error('Task must be prepared before it can run.');

            return self::FAILURE;
        }

        $promptPath = $prompts->save($task);
        if (! $runner->isAvailable()) {
            $runner->recordUnavailable($task);
            $reviews->collect($task->refresh());
            $this->error('OpenCode CLI was not found. Task is blocked; prompt and artifacts were retained.');

            return self::FAILURE;
        }

        try {
            $exitCode = $runner->run($task, $promptPath);
            $reviewPath = $reviews->collect($task->refresh());
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Review: {$reviewPath}");

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
