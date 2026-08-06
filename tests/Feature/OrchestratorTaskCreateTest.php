<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrchestratorTaskCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_task_for_a_registered_project(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'sample',
            'repo_path' => 'C:\\workspace\\sample',
            'default_branch' => 'main',
        ]);

        $this->artisan('orchestrator:task-create', [
            'project' => 'sample',
            'title' => 'Add command',
            '--description' => 'Create a useful command.',
            '--acceptance' => 'Command is registered.',
            '--autonomy' => 'medium',
        ])->assertSuccessful();

        $this->assertDatabaseHas('orchestrator_tasks', [
            'project_id' => $project->id,
            'title' => 'Add command',
            'status' => 'draft',
        ]);
    }
}
