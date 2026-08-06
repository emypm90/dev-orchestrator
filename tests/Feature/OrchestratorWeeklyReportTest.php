<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorWeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_outputs_and_saves_a_project_grouped_weekly_report(): void
    {
        Storage::fake('local');
        $since = now()->toImmutable()->startOfWeek();
        $until = now()->toImmutable()->endOfWeek();
        $project = OrchestratorProject::create([
            'name' => 'api',
            'repo_path' => sys_get_temp_dir(),
            'default_branch' => 'main',
        ]);
        $completed = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Ship archive command',
            'status' => 'archived',
        ]);
        $completed->update(['archived_at' => $since->addDay()->setTime(10, 0)]);
        $completed->update(['last_verification_status' => 'passed']);
        $completed->update(['review_decision' => 'approved', 'reviewed_at' => $since->addDay()]);
        $blocked = OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Decide retention period',
            'status' => 'needs_decision',
        ]);
        $blocked->update(['updated_at' => $since->addDays(2)->setTime(10, 0)]);

        $archivedAt = $completed->fresh()->archived_at;
        $this->assertNotNull($archivedAt);
        $this->assertTrue($archivedAt->betweenIncluded($since->startOfDay(), $until->endOfDay()));
        $this->artisan('orchestrator:weekly-report', [
            '--since' => $since->toDateString(),
            '--until' => $until->toDateString(),
            '--save' => true,
        ])
            ->expectsOutputToContain('Ship archive command')
            ->assertSuccessful();

        $path = 'orchestrator/reports/weekly-'.$until->toDateString().'.md';
        Storage::disk('local')->assertExists($path);
        $report = Storage::disk('local')->get($path);
        $this->assertStringContainsString('### api', $report);
        $this->assertStringContainsString('Completed / approved / archived work', $report);
        $this->assertStringContainsString('Blocked / failed / needs decision', $report);
        $this->assertStringContainsString('Decide retention period', $report);
        $this->assertStringContainsString('Resolve blockers or decisions for', $report);
        $this->assertStringContainsString('verification: passed', $report);
        $this->assertStringContainsString('review: approved', $report);
    }
}
