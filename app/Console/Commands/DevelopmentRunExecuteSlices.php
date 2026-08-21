<?php

namespace App\Console\Commands;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunAgentStageService;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\StageAgentContract;
use Illuminate\Console\Command;

class DevelopmentRunExecuteSlices extends Command
{
    protected $signature = 'development-run:execute-slices {run : Development Run ID}';

    protected $description = 'Execute a Development Run Slices stage in the background';

    public function handle(DevelopmentRunAgentStageService $executor, OpenCodeExecutionRunner $runner, StageAgentContract $contract): int
    {
        $run = DevelopmentRun::find($this->argument('run'));
        if (! $run) {
            $this->error('Development Run not found.');

            return self::FAILURE;
        }

        $executor->executeSlices($run, $runner, $contract);
        $this->info('Slices execution finished.');

        return self::SUCCESS;
    }
}
