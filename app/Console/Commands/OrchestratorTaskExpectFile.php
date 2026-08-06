<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ExpectedFilePath;
use Illuminate\Console\Command;
use InvalidArgumentException;

class OrchestratorTaskExpectFile extends Command
{
    protected $signature = 'orchestrator:task-expect-file {task : Task ID} {file : Relative expected file path}';

    protected $description = 'Add an expected file to a task acceptance check';

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

        $files = array_values(array_unique([...( $task->expected_files ?? []), $file]));
        $task->update(['expected_files' => $files]);
        $this->info("Expected file recorded for task {$task->id}: {$file}");

        return self::SUCCESS;
    }
}
