<?php

namespace App\Console\Commands;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunAgentStageService;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\StageAgentContract;
use Illuminate\Console\Command;

class DevelopmentRunExecutePlan extends Command
{
    protected $signature = 'development-run:execute-plan {run : Development Run ID}';

    protected $description = 'Execute a Development Run Plan stage in the background';

    public function handle(DevelopmentRunAgentStageService $executor, OpenCodeExecutionRunner $runner, StageAgentContract $contract): int
    {
        $run = DevelopmentRun::find($this->argument('run'));
        if (! $run) {
            $this->error('Development Run not found.');

            return self::FAILURE;
        }

        $executor->executePlan($run, $runner, $contract);
        $this->info('Plan execution finished.');

        return self::SUCCESS;
    }
}
