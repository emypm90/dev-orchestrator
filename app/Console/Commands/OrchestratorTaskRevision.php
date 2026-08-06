<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ReviewDecisionRecorder;
use Illuminate\Console\Command;

class OrchestratorTaskRevision extends Command
{
    protected $signature = 'orchestrator:task-revision {task : Task ID} {--reason= : Revision reason}';

    protected $description = 'Request revisions after human review without changing Git state';

    public function handle(ReviewDecisionRecorder $decisions): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        $path = $decisions->record($task, 'needs_revision', $this->option('reason') ?: 'No reason provided.');
        $this->info("Task {$task->id} needs revision. Decision artifact: {$path}");

        return self::SUCCESS;
    }
}
