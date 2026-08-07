<?php

namespace App\Console\Commands;

use App\Models\OrchestratorTask;
use App\Services\Orchestrator\TaskStatusPresenter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrchestratorTaskStatus extends Command
{
    public function __construct(private readonly TaskStatusPresenter $presenter)
    {
        parent::__construct();
    }

    protected $signature = 'orchestrator:task-status
                            {task? : Optional task ID}
                            {--project= : Filter by project name}
                            {--status= : Filter by task status}
                            {--attention : Show only tasks needing attention}
                            {--limit=25 : Maximum tasks to show in the dashboard}';

    protected $description = 'Show task status dashboard or one task in detail';

    public function handle(): int
    {
        if ($this->argument('task') === null && (int) $this->option('limit') < 1) {
            $this->error('The --limit option must be at least 1.');

            return self::FAILURE;
        }

        $query = OrchestratorTask::with('project')->orderByDesc('updated_at');

        if ($taskId = $this->argument('task')) {
            $query->whereKey($taskId);
        } else {
            $this->applyFilters($query);
        }

        $tasks = $query->get();
        if ($tasks->isEmpty()) {
            $this->warn('No matching tasks found.');

            return self::SUCCESS;
        }

        if ($taskId) {
            $this->showDetail($tasks->first());

            return self::SUCCESS;
        }

        $this->showDashboard($tasks);

        return self::SUCCESS;
    }

    private function applyFilters(Builder $query): void
    {
        if ($project = $this->option('project')) {
            $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->where('name', $project));
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        if ($this->option('attention')) {
            $query->where(function (Builder $attentionQuery): void {
                $attentionQuery->whereIn('status', ['running', 'blocked', 'needs_revision'])
                    ->orWhere('last_verification_status', 'failed')
                    ->orWhere('last_acceptance_status', 'failed')
                    ->orWhere(function (Builder $reviewQuery): void {
                        $reviewQuery->where('status', 'completed')->whereNull('review_decision');
                    });
            });
        }
    }

    /** @param Collection<int, OrchestratorTask> $tasks */
    private function showDashboard(Collection $tasks): void
    {
        $limit = (int) $this->option('limit');
        $shown = $tasks->take($limit);
        $this->info('Task dashboard');
        $this->line('Tasks: '.$tasks->count().' | Showing: '.$shown->count());
        $this->line('By status: '.$tasks->countBy('status')->sortKeys()->map(fn (int $count, string $status) => "{$status} {$count}")->implode(', '));
        $this->line('Attention: human review '.$tasks->filter(fn (OrchestratorTask $task) => $this->presenter->needsHumanReview($task))->count()
            .' | failed verification '.$tasks->where('last_verification_status', 'failed')->count()
            .' | failed acceptance '.$tasks->where('last_acceptance_status', 'failed')->count()
            .' | needs revision '.$tasks->where('status', 'needs_revision')->count()
            .' | running '.$tasks->where('status', 'running')->count()
            .' | blocked '.$tasks->where('status', 'blocked')->count());

        $this->newLine();
        $this->table(['ID', 'Project', 'Title', 'Status', 'Review', 'Verify', 'Accept', 'Updated', 'Next action'], $shown->map(fn (OrchestratorTask $task) => [
            $task->id,
            $task->project->name,
            Str::limit($task->title, 36),
            $task->status,
            $task->review_decision ?? '-',
            $task->last_verification_status ?? '-',
            $task->last_acceptance_status ?? '-',
            $task->updated_at->diffForHumans(),
            $this->presenter->nextAction($task),
        ])->all());
    }

    private function showDetail(OrchestratorTask $task): void
    {
        $this->info("Task #{$task->id}");
        $this->table(['Field', 'Value'], [
            ['Project', $task->project->name],
            ['Title', $task->title],
            ['Status', $task->status],
            ['Branch', $task->branch_name ?? '-'],
            ['Worktree', $task->worktree_path ?? '-'],
            ['Review decision', $task->review_decision ?? '-'],
            ['Reviewed', $task->reviewed_at?->toDateTimeString() ?? '-'],
            ['Review notes', $task->review_notes ?? '-'],
            ['Verification', $task->last_verification_status ?? '-'],
            ['Verified', $task->last_verified_at?->toDateTimeString() ?? '-'],
            ['Verification artifact', $task->last_verification_path ?? '-'],
            ['Acceptance', $task->last_acceptance_status ?? '-'],
            ['Acceptance checked', $task->last_acceptance_checked_at?->toDateTimeString() ?? '-'],
            ['Acceptance artifact', $task->last_acceptance_path ?? '-'],
            ['Archived', $task->archived_at?->toDateTimeString() ?? '-'],
            ['Archive artifact', $task->archive_path ?? '-'],
            ['Expected files', count($task->expected_files ?? [])],
            ['Forbidden files', count($task->forbidden_files ?? [])],
            ['Expected texts', count($task->expected_texts ?? [])],
            ['Expected regexes', count($task->expected_regexes ?? [])],
            ['Next action', $this->presenter->nextAction($task)],
        ]);
    }

}
