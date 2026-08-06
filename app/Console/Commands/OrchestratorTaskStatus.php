<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use Illuminate\Console\Command;

class OrchestratorTaskStatus extends Command
{
    protected $signature = 'orchestrator:task-status {task? : Optional task ID}';

    protected $description = 'Show task statuses and isolated worktree locations';

    public function handle(): int
    {
        $query = OrchestratorTask::with('project')->orderByDesc('id');
        if ($this->argument('task') !== null) {
            $query->whereKey($this->argument('task'));
        }

        $tasks = $query->get();
        if ($tasks->isEmpty()) {
            $this->warn('No matching tasks found.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Project', 'Title', 'Status', 'Branch', 'Worktree'], $tasks->map(fn (OrchestratorTask $task) => [
            $task->id,
            $task->project->name,
            $task->title,
            $task->status,
            $task->branch_name ?? '-',
            $task->worktree_path ?? '-',
        ])->all());

        return self::SUCCESS;
    }
}
