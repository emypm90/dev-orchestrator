<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\PromptBuilder;
use App\Services\Orchestrator\WorktreeService;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorTaskPrepare extends Command
{
    protected $signature = 'orchestrator:task-prepare {task : Task ID}';

    protected $description = 'Create the task branch, worktree, and OpenCode prompt artifact';

    public function handle(WorktreeService $worktrees, PromptBuilder $prompts): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        try {
            $task = $worktrees->prepare($task);
            $promptPath = $prompts->save($task);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Prepared {$task->branch_name}");
        $this->line("Worktree: {$task->worktree_path}");
        $this->line("Prompt: {$promptPath}");

        return self::SUCCESS;
    }
}
