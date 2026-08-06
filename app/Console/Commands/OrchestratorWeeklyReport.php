<?php

namespace App\Console\Commands;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\WeeklyReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class OrchestratorWeeklyReport extends Command
{
    protected $signature = 'orchestrator:weekly-report
        {--since= : Start date (YYYY-MM-DD); defaults to this week\'s Monday}
        {--until= : End date (YYYY-MM-DD); defaults to now}
        {--project= : Optional registered project name}
        {--save : Save the report artifact}';

    protected $description = 'Create a weekly task status report for team review';

    public function handle(WeeklyReportBuilder $reports): int
    {
        try {
            $since = $this->dateOption('since') ?? now()->toImmutable()->startOfWeek();
            $until = $this->dateOption('until') ?? now()->toImmutable();
        } catch (Throwable) {
            $this->error('Dates must use YYYY-MM-DD format.');

            return self::FAILURE;
        }

        if ($since->greaterThan($until)) {
            $this->error('--since must be on or before --until.');

            return self::FAILURE;
        }

        $query = OrchestratorTask::with('project');
        if ($projectName = $this->option('project')) {
            $project = OrchestratorProject::where('name', $projectName)->first();
            if ($project === null) {
                $this->error("Unknown project: {$projectName}");

                return self::FAILURE;
            }
            $query->where('project_id', $project->id);
        }

        $tasks = $query->get()->filter(function (OrchestratorTask $task) use ($since, $until): bool {
            $date = $task->archived_at ?? $task->finished_at ?? $task->updated_at ?? $task->created_at;

            return $date->betweenIncluded($since->startOfDay(), $until->endOfDay());
        });
        $report = $reports->build($tasks, $since, $until);
        $this->line($report);

        if ($this->option('save')) {
            $this->info('Report artifact: '.$reports->save($report, $until));
        }

        return self::SUCCESS;
    }

    private function dateOption(string $option): ?CarbonImmutable
    {
        $value = $this->option($option);
        if ($value === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->toDateString() !== $value) {
            throw new \InvalidArgumentException('Invalid date.');
        }

        return $date;
    }
}
