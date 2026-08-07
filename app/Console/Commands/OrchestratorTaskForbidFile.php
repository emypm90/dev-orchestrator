<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ExpectedFilePath;
use Illuminate\Console\Command;
use InvalidArgumentException;

class OrchestratorTaskForbidFile extends Command
{
    protected $signature = 'orchestrator:task-forbid-file {task : Task ID} {file : Relative forbidden file path}';

    protected $description = 'Add a forbidden file to a task acceptance check';

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

        $files = array_values(array_unique([...($task->forbidden_files ?? []), $file]));
        $task->update(['forbidden_files' => $files]);
        $this->info("Forbidden file recorded for task {$task->id}: {$file}");

        return self::SUCCESS;
    }
}
