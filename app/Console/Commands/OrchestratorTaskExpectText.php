<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ExpectedFilePath;
use Illuminate\Console\Command;
use InvalidArgumentException;

class OrchestratorTaskExpectText extends Command
{
    protected $signature = 'orchestrator:task-expect-text {task : Task ID} {file : Relative file path} {text : Literal text required in the file}';

    protected $description = 'Add a literal text expectation to a task acceptance check';

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

        $expectation = ['file' => $file, 'text' => $this->argument('text')];
        $expectations = $task->expected_texts ?? [];
        if (! in_array($expectation, $expectations, true)) {
            $expectations[] = $expectation;
        }

        $task->update(['expected_texts' => $expectations]);
        $this->info("Expected text recorded for task {$task->id}: {$file}");

        return self::SUCCESS;
    }
}
