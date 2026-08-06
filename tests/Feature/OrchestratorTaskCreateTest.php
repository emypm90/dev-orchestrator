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
            '--expected-file' => ['docs/export.md', '.\\README.md', 'docs/export.md'],
        ])->assertSuccessful();

        $this->assertDatabaseHas('orchestrator_tasks', [
            'project_id' => $project->id,
            'title' => 'Add command',
            'status' => 'draft',
        ]);
        $this->assertSame(['docs/export.md', 'README.md'], $project->tasks()->firstOrFail()->expected_files);
    }

    public function test_it_rejects_absolute_and_traversal_expected_file_paths(): void
    {
        OrchestratorProject::create([
            'name' => 'sample',
            'repo_path' => 'C:\\workspace\\sample',
            'default_branch' => 'main',
        ]);

        $this->artisan('orchestrator:task-create', [
            'project' => 'sample',
            'title' => 'Unsafe expected file',
            '--expected-file' => 'C:\\outside.md',
        ])->expectsOutputToContain('must be relative')->assertFailed();

        $this->artisan('orchestrator:task-create', [
            'project' => 'sample',
            'title' => 'Traversal expected file',
            '--expected-file' => 'docs/../outside.md',
        ])->expectsOutputToContain('cannot contain ".."')->assertFailed();
    }
}
