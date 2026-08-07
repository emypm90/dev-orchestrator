<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\AcceptanceChecker;
use Illuminate\Console\Command;

class OrchestratorTaskAcceptanceCheck extends Command
{
    protected $signature = 'orchestrator:task-acceptance-check {task : Task ID}';

    protected $description = 'Check a task has its configured expected and forbidden files';

    public function handle(AcceptanceChecker $checks): int
    {
        $task = OrchestratorTask::with('project')->find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        $result = $checks->check($task);
        $this->line("Acceptance status: {$result['status']}");
        $this->line("Directory used: {$result['directory']}");
        $this->line("Acceptance artifact: {$result['path']}");

        return $result['status'] === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}
