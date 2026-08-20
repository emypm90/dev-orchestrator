<?php

namespace App\Console\Commands;

use App\Models\DevelopmentRun;
use App\Services\DevelopmentRuns\DevelopmentRunExecutionService;
use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use Illuminate\Console\Command;

class DevelopmentRunExecuteBuild extends Command
{
    protected $signature = 'development-run:execute-build {run : Development Run ID}';

    protected $description = 'Execute a Development Run Build stage in the background';

    public function handle(DevelopmentRunExecutionService $executor, OpenCodeExecutionRunner $runner): int
    {
        $run = DevelopmentRun::find($this->argument('run'));
        if (! $run) {
            $this->error('Development Run not found.');

            return self::FAILURE;
        }

        $executor->executeBuild($run, $runner);
        $this->info('Build execution finished.');

        return self::SUCCESS;
    }
}
