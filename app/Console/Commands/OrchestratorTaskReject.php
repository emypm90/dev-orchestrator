<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ReviewDecisionRecorder;
use Illuminate\Console\Command;

class OrchestratorTaskReject extends Command
{
    protected $signature = 'orchestrator:task-reject {task : Task ID} {--reason= : Rejection reason}';

    protected $description = 'Reject a task after human review without changing Git state';

    public function handle(ReviewDecisionRecorder $decisions): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        $path = $decisions->record($task, 'rejected', $this->option('reason') ?: 'No reason provided.');
        $this->info("Task {$task->id} rejected. Decision artifact: {$path}");

        return self::SUCCESS;
    }
}
