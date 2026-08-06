<?php

namespace App\Console\Commands;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ExpectedFilePath;
use Illuminate\Console\Command;

class OrchestratorTaskCreate extends Command
{
    protected $signature = 'orchestrator:task-create
        {project : Registered project name}
        {title : Short task title}
        {--description= : Task context and requested work}
        {--acceptance= : Acceptance criteria}
        {--autonomy=medium : low, medium, or high}
        {--expected-file=* : Relative file required for acceptance; repeat option for multiple files}';

    protected $description = 'Create a task for a registered project';

    public function handle(ExpectedFilePath $paths): int
    {
        $project = OrchestratorProject::where('name', $this->argument('project'))->first();
        if ($project === null) {
            $this->error("Unknown project: {$this->argument('project')}");

            return self::FAILURE;
        }

        $autonomy = $this->option('autonomy');
        if (! in_array($autonomy, ['low', 'medium', 'high'], true)) {
            $this->error('Autonomy must be low, medium, or high.');

            return self::FAILURE;
        }

        try {
            $expectedFiles = array_values(array_unique(array_map($paths->normalize(...), (array) $this->option('expected-file'))));
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $task = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => $this->argument('title'),
            'description' => $this->option('description'),
            'acceptance_criteria' => $this->option('acceptance'),
            'autonomy' => $autonomy,
            'expected_files' => $expectedFiles === [] ? null : $expectedFiles,
        ]);

        $this->info("Created task #{$task->id}: {$task->title}");

        return self::SUCCESS;
    }
}
