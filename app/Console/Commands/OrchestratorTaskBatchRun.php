<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\BatchTaskRunner;
use App\Services\Orchestrator\PromptBuilder;
use App\Services\Orchestrator\WorktreeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrchestratorTaskBatchRun extends Command
{
    protected $signature = 'orchestrator:task-batch-run {tasks* : Task IDs} {--concurrency=2 : Max concurrent tasks} {--prepare : Prepare draft tasks before running} {--verify : Run verification after task run} {--review : Collect review artifact after task run}';

    protected $description = 'Run independent prepared tasks with a controlled OpenCode concurrency limit';

    public function handle(WorktreeService $worktrees, PromptBuilder $prompts, BatchTaskRunner $runner): int
    {
        $concurrency = filter_var($this->option('concurrency'), FILTER_VALIDATE_INT);
        if ($concurrency === false || $concurrency < 1 || $concurrency > 4) {
            $this->error('Concurrency must be an integer between 1 and 4.');

            return self::FAILURE;
        }

        $taskIds = array_map('intval', $this->argument('tasks'));
        if (count($taskIds) !== count(array_unique($taskIds))) {
            $this->error('Each task ID may be provided only once per batch.');

            return self::FAILURE;
        }

        $tasks = OrchestratorTask::with('project')->whereKey($taskIds)->get()->keyBy('id');
        $missing = array_values(array_diff($taskIds, $tasks->keys()->all()));
        if ($missing !== []) {
            $this->error('Task not found: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        $jobs = [];
        foreach ($taskIds as $taskId) {
            $task = $tasks[$taskId];
            if (in_array($task->status, ['running', 'archived'], true)) {
                $this->error("Task {$task->id} is {$task->status} and cannot be included in a batch.");

                return self::FAILURE;
            }

            if ($task->status === 'draft' && ! $this->option('prepare')) {
                $this->error("Task {$task->id} is draft. Use --prepare to create its isolated worktree first.");

                return self::FAILURE;
            }

            if ($task->status === 'completed') {
                $this->warn("Task {$task->id} is completed and was skipped. Run verify or review manually before archiving.");

                continue;
            }

            if ($task->status === 'draft') {
                try {
                    $task = $worktrees->prepare($task);
                    $this->line("Prepared task {$task->id}: {$task->worktree_path}");
                } catch (Throwable $exception) {
                    $this->error("Task {$task->id} could not be prepared: {$exception->getMessage()}");

                    return self::FAILURE;
                }
            }

            if ($task->worktree_path === null || ! is_dir($task->worktree_path)) {
                $this->error("Task {$task->id} must have an existing isolated worktree before it can run.");

                return self::FAILURE;
            }

            $jobs[] = ['task' => $task, 'prompt_path' => $prompts->save($task)];
        }

        if ($jobs === []) {
            $this->warn('No eligible tasks were run.');

            return self::SUCCESS;
        }

        $startedAt = now();
        $results = $runner->run($jobs, $concurrency, $this->option('verify'), $this->option('review'));
        $path = $this->saveArtifact($taskIds, $concurrency, $startedAt, now(), $results);
        $failed = collect($results)->contains(fn (array $result): bool => $result['status'] !== 'completed');

        $this->line("Batch artifact: {$path}");
        if ($failed) {
            $this->error('Batch completed with blocked or failed tasks. See the batch artifact for next actions.');

            return self::FAILURE;
        }

        $this->info('Batch completed. Review and verify results before archiving.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $taskIds
     * @param  array<int, array{status: string, exit_code: ?int, prompt_path: string, run_path: string, verification_path: ?string, review_path: ?string, next_action: string}>  $results
     */
    private function saveArtifact(array $taskIds, int $concurrency, $startedAt, $finishedAt, array $results): string
    {
        $path = 'orchestrator/batches/'.$startedAt->format('Ymd-His-u').'/batch.md';
        $markdown = "# Task Batch Run\n\n"
            .'- Task IDs: '.implode(', ', $taskIds)."\n"
            ."- Concurrency: {$concurrency}\n"
            ."- Started: {$startedAt->toIso8601String()}\n"
            ."- Finished: {$finishedAt->toIso8601String()}\n\n"
            ."| Task | Status | Exit | Prompt | Run log | Verification | Review | Next action |\n"
            ."| --- | --- | --- | --- | --- | --- | --- | --- |\n";

        foreach ($results as $taskId => $result) {
            $markdown .= "| {$taskId} | {$result['status']} | ".($result['exit_code'] ?? '-')." | {$result['prompt_path']} | {$result['run_path']} | ".($result['verification_path'] ?? '-')." | ".($result['review_path'] ?? '-')." | {$result['next_action']} |\n";
        }

        Storage::disk('local')->put($path, $markdown);

        return Storage::disk('local')->path($path);
    }
}
