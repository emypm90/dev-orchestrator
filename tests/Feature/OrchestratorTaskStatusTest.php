<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrchestratorTaskStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_dashboard_with_summary_attention_and_actionable_rows(): void
    {
        $project = $this->project('alpha');
        OrchestratorTask::create(['project_id' => $project->id, 'title' => 'Prepare export', 'status' => 'draft']);
        OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Review failed task',
            'status' => 'completed',
            'last_verification_status' => 'failed',
            'last_acceptance_status' => 'passed',
        ]);

        $this->artisan('orchestrator:task-status')
            ->expectsOutputToContain('Task dashboard')
            ->expectsOutputToContain('By status: completed 1, draft 1')
            ->expectsOutputToContain('Attention: human review 1 | failed verification 1')
            ->expectsOutputToContain('Prepare task.')
            ->expectsOutputToContain('Fix verification failure, then rerun.')
            ->doesntExpectOutputToContain('Worktree')
            ->assertSuccessful();
    }

    public function test_it_filters_the_attention_queue_and_shows_task_details(): void
    {
        $alpha = $this->project('alpha');
        $beta = $this->project('beta');
        OrchestratorTask::create(['project_id' => $alpha->id, 'title' => 'Normal task', 'status' => 'draft']);
        $attention = OrchestratorTask::create([
            'project_id' => $beta->id,
            'title' => 'Needs a decision',
            'status' => 'completed',
            'branch_name' => 'ai/task-2-needs-a-decision',
            'worktree_path' => 'C:\\worktrees\\task-2',
            'last_verification_status' => 'passed',
            'last_verification_path' => 'C:\\artifacts\\verification.md',
            'last_acceptance_status' => 'passed',
            'last_acceptance_path' => 'C:\\artifacts\\acceptance.md',
            'expected_files' => ['docs/guide.md'],
            'forbidden_files' => ['README.md'],
            'expected_texts' => [['file' => 'docs/guide.md', 'text' => 'Guide']],
            'expected_regexes' => [['file' => 'docs/guide.md', 'pattern' => '/Guide/']],
        ]);

        $this->artisan('orchestrator:task-status', ['--attention' => true, '--project' => 'beta'])
            ->expectsOutputToContain('Tasks: 1 | Showing: 1')
            ->expectsOutputToContain('Needs a decision')
            ->doesntExpectOutputToContain('Normal task')
            ->assertSuccessful();

        $this->artisan('orchestrator:task-status', ['task' => $attention->id])
            ->expectsOutputToContain('Task #'.$attention->id)
            ->expectsOutputToContain('C:\\worktrees\\task-2')
            ->expectsOutputToContain('Expected files')
            ->expectsOutputToContain('Review artifacts, then approve, reject, or request revision.')
            ->assertSuccessful();
    }

    public function test_it_rejects_a_non_positive_dashboard_limit(): void
    {
        $this->artisan('orchestrator:task-status', ['--limit' => 0])
            ->expectsOutputToContain('The --limit option must be at least 1.')
            ->assertFailed();
    }

    private function project(string $name): OrchestratorProject
    {
        return OrchestratorProject::create([
            'name' => $name,
            'repo_path' => 'C:\\workspace\\'.$name,
            'default_branch' => 'main',
        ]);
    }
}
