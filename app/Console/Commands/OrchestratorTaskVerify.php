<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\VerificationRunner;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorTaskVerify extends Command
{
    protected $signature = 'orchestrator:task-verify {task : Task ID} {--test : Run only test command} {--lint : Run only lint command}';

    protected $description = 'Run configured test and lint commands and retain their results';

    public function handle(VerificationRunner $verification): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        try {
            $result = $verification->run($task, $this->option('test'), $this->option('lint'));
        } catch (Throwable $exception) {
            $this->error("Verification failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->line("Verification artifact: {$result['path']}");

        if ($result['status'] === 'skipped') {
            $this->error('No configured test or lint command matched the selected options.');

            return self::FAILURE;
        }

        if ($result['status'] !== 'passed') {
            $this->error("Verification {$result['status']}. See the artifact for command output.");

            return self::FAILURE;
        }

        $this->info('Verification passed.');

        return self::SUCCESS;
    }
}
