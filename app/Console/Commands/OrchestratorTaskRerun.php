<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\RevisionRerunRunner;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorTaskRerun extends Command
{
    protected $signature = 'orchestrator:task-rerun {task : Task ID} {--instructions= : Additional revision instructions} {--verify : Run verification after rerun} {--review : Collect review after rerun} {--acceptance : Run expected-file acceptance checks after rerun}';

    protected $description = 'Rerun a revision, failed, or blocked task without changing Git state';

    public function handle(RevisionRerunRunner $reruns): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        if ($task->status === 'completed') {
            $this->error('Completed tasks must be marked needs_revision before rerunning. Use orchestrator:task-revision first.');

            return self::FAILURE;
        }

        if (in_array($task->status, ['approved', 'archived', 'running'], true)) {
            $this->error("Task {$task->id} is {$task->status} and cannot be rerun.");

            return self::FAILURE;
        }

        if (! in_array($task->status, ['needs_revision', 'failed', 'blocked'], true)) {
            $this->error("Task {$task->id} is {$task->status}; only needs_revision, failed, or blocked tasks can be rerun.");

            return self::FAILURE;
        }

        try {
            $result = $reruns->run($task, $this->option('instructions'), $this->option('verify'), $this->option('review'), $this->option('acceptance'));
        } catch (Throwable $exception) {
            $this->error("Rerun failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->line("Revision prompt: {$result['prompt_path']}");
        $this->line("Rerun artifact: {$result['rerun_path']}");
        if ($result['verification_path'] !== null) {
            $this->line("Verification artifact: {$result['verification_path']}");
        }
        if ($result['review_path'] !== null) {
            $this->line("Review artifact: {$result['review_path']}");
        }
        if ($result['acceptance_path'] !== null) {
            $this->line("Acceptance artifact: {$result['acceptance_path']}");
        }

        return $result['exit_code'] === 0 && ($result['acceptance_status'] === null || $result['acceptance_status'] === 'passed') ? self::SUCCESS : self::FAILURE;
    }
}
