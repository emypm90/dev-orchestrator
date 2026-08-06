<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ReviewDecisionRecorder;
use Illuminate\Console\Command;

class OrchestratorTaskApprove extends Command
{
    protected $signature = 'orchestrator:task-approve {task : Task ID} {--notes= : Optional approval notes}';

    protected $description = 'Approve a task after human review without changing Git state';

    public function handle(ReviewDecisionRecorder $decisions): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        $path = $decisions->record($task, 'approved', $this->option('notes') ?: 'No notes provided.');
        $this->info("Task {$task->id} approved. Decision artifact: {$path}");

        return self::SUCCESS;
    }
}
