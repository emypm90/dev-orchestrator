<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ExpectedFilePath;
use Illuminate\Console\Command;
use InvalidArgumentException;

class OrchestratorTaskExpectRegex extends Command
{
    protected $signature = 'orchestrator:task-expect-regex {task : Task ID} {file : Relative file path} {pattern : PCRE pattern required to match the file}';

    protected $description = 'Add a regex expectation to a task acceptance check';

    public function handle(ExpectedFilePath $paths): int
    {
        $task = OrchestratorTask::find($this->argument('task'));
        if ($task === null) {
            $this->error('Task not found.');

            return self::FAILURE;
        }

        try {
            $file = $paths->normalize($this->argument('file'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $expectation = ['file' => $file, 'pattern' => $this->argument('pattern')];
        $expectations = $task->expected_regexes ?? [];
        if (! in_array($expectation, $expectations, true)) {
            $expectations[] = $expectation;
        }

        $task->update(['expected_regexes' => $expectations]);
        $this->info("Expected regex recorded for task {$task->id}: {$file}");

        return self::SUCCESS;
    }
}
