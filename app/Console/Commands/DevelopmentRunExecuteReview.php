<?php

namespace App\Console\Commands;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunAgentStageService;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use App\Services\DevelopmentRuns\StageAgentContract;
use Illuminate\Console\Command;

class DevelopmentRunExecuteReview extends Command
{
    protected $signature = 'development-run:execute-review {run : Development Run ID}';

    protected $description = 'Execute a Development Run Review stage in the background';

    public function handle(DevelopmentRunAgentStageService $executor, OpenCodeExecutionRunner $runner, StageAgentContract $contract): int
    {
        $run = DevelopmentRun::find($this->argument('run'));
        if (! $run) {
            $this->error('Development Run not found.');

            return self::FAILURE;
        }

        $executor->executeReview($run, $runner, $contract);
        $this->info('Review execution finished.');

        return self::SUCCESS;
    }
}
