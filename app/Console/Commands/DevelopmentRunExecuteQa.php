<?php

namespace App\Console\Commands;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunExecutionService;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\QaExecutionRunner;
use App\Services\DevelopmentRuns\StageAgentContract;
use Illuminate\Console\Command;

class DevelopmentRunExecuteQa extends Command
{
    protected $signature = 'development-run:execute-qa {run : Development Run ID}';

    protected $description = 'Execute a Development Run QA stage in the background';

    public function handle(DevelopmentRunExecutionService $executor, QaExecutionRunner $qaRunner, OpenCodeExecutionRunner $openCodeRunner, StageAgentContract $contract): int
    {
        $run = DevelopmentRun::find($this->argument('run'));
        if (! $run) {
            $this->error('Development Run not found.');

            return self::FAILURE;
        }

        $executor->executeQa($run, $qaRunner, $openCodeRunner, $contract);
        $this->info('QA execution finished.');

        return self::SUCCESS;
    }
}
