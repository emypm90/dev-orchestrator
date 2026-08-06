<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class WeeklyReportBuilder
{
    /**
     * @param  Collection<int, OrchestratorTask>  $tasks
     */
    public function build(Collection $tasks, CarbonImmutable $since, CarbonImmutable $until): string
    {
        $sections = [
            'Completed / approved / archived work' => ['completed', 'approved', 'archived'],
            'In progress / running / prepared' => ['in_progress', 'running', 'prepared'],
            'Blocked / failed / needs decision' => ['blocked', 'failed', 'needs_decision', 'rejected', 'needs_revision'],
            'Planned / draft' => ['planned', 'draft'],
        ];

        $report = "# Weekly Dev Orchestrator Report\n\n"
            ."Period: {$since->toDateString()} to {$until->toDateString()}\n";

        foreach ($sections as $heading => $statuses) {
            $report .= "\n## {$heading}\n".$this->taskList($tasks->whereIn('status', $statuses));
        }

        return $report."\n## Suggested focus for this week\n".$this->suggestedFocus($tasks)."\n";
    }

    public function save(string $report, CarbonImmutable $until): string
    {
        $path = 'orchestrator/reports/weekly-'.$until->toDateString().'.md';
        Storage::disk('local')->put($path, $report);

        return Storage::disk('local')->path($path);
    }

    /**
     * @param  Collection<int, OrchestratorTask>  $tasks
     */
    private function taskList(Collection $tasks): string
    {
        if ($tasks->isEmpty()) {
            return "No tasks.\n";
        }

        return $tasks->groupBy(fn (OrchestratorTask $task) => $task->project->name)
            ->map(function (Collection $projectTasks, string $project): string {
                $items = $projectTasks->map(function (OrchestratorTask $task): string {
                    $verification = $task->last_verification_status === null ? '' : "; verification: {$task->last_verification_status}";
                    $acceptance = $task->last_acceptance_status === null ? '' : "; acceptance: {$task->last_acceptance_status}";
                    $decision = $task->review_decision === null ? '' : "; review: {$task->review_decision}";

                    return "- [{$task->status}] #{$task->id} {$task->title} (".$this->taskDate($task)->toDateString()."){$verification}{$acceptance}{$decision}";
                })->implode("\n");

                return "### {$project}\n{$items}";
            })->implode("\n\n")."\n";
    }

    /**
     * @param  Collection<int, OrchestratorTask>  $tasks
     */
    private function suggestedFocus(Collection $tasks): string
    {
        $blocked = $tasks->whereIn('status', ['blocked', 'failed', 'needs_decision', 'rejected', 'needs_revision']);
        if ($blocked->isNotEmpty()) {
            return 'Resolve blockers or decisions for: '.$blocked->map(fn (OrchestratorTask $task) => "#{$task->id}")->implode(', ').".\n";
        }

        $active = $tasks->whereIn('status', ['in_progress', 'running', 'prepared']);
        if ($active->isNotEmpty()) {
            return 'Finish and review active tasks: '.$active->map(fn (OrchestratorTask $task) => "#{$task->id}")->implode(', ').".\n";
        }

        $planned = $tasks->whereIn('status', ['planned', 'draft']);
        if ($planned->isNotEmpty()) {
            return 'Prepare the next planned tasks: '.$planned->map(fn (OrchestratorTask $task) => "#{$task->id}")->implode(', ').".\n";
        }

        $completed = $tasks->whereIn('status', ['completed', 'approved', 'archived']);
        if ($completed->isNotEmpty()) {
            return "Review completed work, capture weekly learnings, and choose the next priorities.\n";
        }

        return "No task activity in this reporting period.\n";
    }

    private function taskDate(OrchestratorTask $task): CarbonImmutable
    {
        return CarbonImmutable::instance($task->archived_at ?? $task->finished_at ?? $task->updated_at ?? $task->created_at);
    }
}
