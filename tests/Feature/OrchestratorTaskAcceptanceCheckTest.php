<?php

namespace Tests\Feature;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrchestratorTaskAcceptanceCheckTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orchestrator-acceptance-test-'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_adds_a_normalized_expected_file_only_once(): void
    {
        $task = $this->task(['docs/guide.md']);

        $this->artisan('orchestrator:task-expect-file', ['task' => $task->id, 'file' => '.\\docs\\guide.md'])->assertSuccessful();
        $this->assertSame(['docs/guide.md'], $task->fresh()->expected_files);

        $this->artisan('orchestrator:task-expect-file', ['task' => $task->id, 'file' => '../outside.md'])
            ->expectsOutputToContain('cannot contain ".."')->assertFailed();

        $this->artisan('orchestrator:task-expect-file', ['task' => 999, 'file' => 'docs/missing.md'])
            ->expectsOutputToContain('Task not found.')->assertFailed();
    }

    public function test_it_adds_a_normalized_forbidden_file_only_once(): void
    {
        $task = $this->task(null);

        $this->artisan('orchestrator:task-forbid-file', ['task' => $task->id, 'file' => '.\\README.md'])->assertSuccessful();
        $this->artisan('orchestrator:task-forbid-file', ['task' => $task->id, 'file' => 'README.md'])->assertSuccessful();
        $this->assertSame(['README.md'], $task->fresh()->forbidden_files);

        $this->artisan('orchestrator:task-forbid-file', ['task' => $task->id, 'file' => '../outside.md'])
            ->expectsOutputToContain('cannot contain ".."')->assertFailed();

        $this->artisan('orchestrator:task-forbid-file', ['task' => 999, 'file' => 'README.md'])
            ->expectsOutputToContain('Task not found.')->assertFailed();
    }

    public function test_it_adds_normalized_text_and_regex_expectations_only_once(): void
    {
        $task = $this->task(null);

        $this->artisan('orchestrator:task-expect-text', ['task' => $task->id, 'file' => '.\\docs\\guide.md', 'text' => 'Required text'])->assertSuccessful();
        $this->artisan('orchestrator:task-expect-text', ['task' => $task->id, 'file' => 'docs/guide.md', 'text' => 'Required text'])->assertSuccessful();
        $this->assertSame([['file' => 'docs/guide.md', 'text' => 'Required text']], $task->fresh()->expected_texts);

        $this->artisan('orchestrator:task-expect-regex', ['task' => $task->id, 'file' => '.\\docs\\guide.md', 'pattern' => '/Required\\s+text/'])->assertSuccessful();
        $this->assertSame([['file' => 'docs/guide.md', 'pattern' => '/Required\\s+text/']], $task->fresh()->expected_regexes);

        $this->artisan('orchestrator:task-expect-text', ['task' => $task->id, 'file' => '../outside.md', 'text' => 'Nope'])
            ->expectsOutputToContain('cannot contain ".."')->assertFailed();
        $this->artisan('orchestrator:task-expect-regex', ['task' => 999, 'file' => 'docs/guide.md', 'pattern' => '/Guide/'])
            ->expectsOutputToContain('Task not found.')->assertFailed();
    }

    public function test_it_records_a_passed_check_from_the_worktree(): void
    {
        Storage::fake('local');
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree';
        File::ensureDirectoryExists($worktree.DIRECTORY_SEPARATOR.'docs');
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'guide.md', "Guide\n");
        $task = $this->task(['docs/guide.md'], $worktree);

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $task->id])->assertSuccessful();

        $task->refresh();
        $this->assertSame('passed', $task->last_acceptance_status);
        $this->assertNotNull($task->last_acceptance_checked_at);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$task->id}/acceptance.md");
        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/acceptance.md");
        $this->assertStringContainsString('Status: passed', $artifact);
        $this->assertStringContainsString('Directory source: task worktree', $artifact);
        $this->assertStringContainsString('`docs/guide.md`', $artifact);
    }

    public function test_it_records_failed_and_skipped_checks_using_the_project_repository_when_needed(): void
    {
        Storage::fake('local');
        $failed = $this->task(['docs/missing.md'], $this->root.DIRECTORY_SEPARATOR.'missing-worktree');

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $failed->id])->assertFailed();
        $failed->refresh();
        $this->assertSame('failed', $failed->last_acceptance_status);
        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$failed->id}/acceptance.md");
        $this->assertStringContainsString('Directory source: project repository', $artifact);
        $this->assertStringContainsString('`docs/missing.md`', $artifact);

        $skipped = $this->task(null, null);
        $this->artisan('orchestrator:task-acceptance-check', ['task' => $skipped->id])->assertFailed();
        $this->assertSame('skipped', $skipped->fresh()->last_acceptance_status);
        Storage::disk('local')->assertExists("orchestrator/tasks/{$skipped->id}/acceptance.md");
    }

    public function test_it_fails_when_configured_forbidden_files_are_tracked_or_untracked_changes(): void
    {
        Storage::fake('local');
        $worktree = $this->gitWorktree();
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'README.md', "# Changed\n");
        (new Process(['git', '-C', $worktree, 'add', 'README.md']))->mustRun();
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'guide.md', "Changed guide\n");
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'UNTRACKED.md', "Unexpected\n");
        $task = $this->task(['docs/guide.md'], $worktree, ['README.md', 'docs/guide.md', 'UNTRACKED.md']);

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $task->id])->assertFailed();

        $task->refresh();
        $this->assertSame('failed', $task->last_acceptance_status);
        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/acceptance.md");
        $this->assertStringContainsString('## Violated forbidden files', $artifact);
        $this->assertStringContainsString('`README.md`', $artifact);
        $this->assertStringContainsString('`docs/guide.md`', $artifact);
        $this->assertStringContainsString('`UNTRACKED.md`', $artifact);
        $this->assertStringContainsString('## Touched files', $artifact);
    }

    public function test_it_passes_when_expected_files_exist_and_forbidden_files_are_untouched(): void
    {
        Storage::fake('local');
        $worktree = $this->gitWorktree();
        $task = $this->task(['docs/guide.md'], $worktree, ['README.md']);

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $task->id])->assertSuccessful();

        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/acceptance.md");
        $this->assertStringContainsString('Status: passed', $artifact);
        $this->assertStringContainsString('## Clean forbidden files', $artifact);
        $this->assertStringContainsString('`README.md`', $artifact);
    }

    public function test_it_passes_content_expectations_and_includes_them_in_the_prompt(): void
    {
        Storage::fake('local');
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree';
        File::ensureDirectoryExists($worktree.DIRECTORY_SEPARATOR.'docs');
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'guide.md', "Guide\nRequired text\n");
        $task = $this->task(null, $worktree, null, [['file' => 'docs/guide.md', 'text' => 'Required text']], [['file' => 'docs/guide.md', 'pattern' => '/^Guide$/m']]);

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $task->id])->assertSuccessful();

        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/acceptance.md");
        $this->assertStringContainsString('## Configured content checks', $artifact);
        $this->assertStringContainsString('## Passed content checks', $artifact);
        $this->assertStringContainsString('docs/guide.md contains literal text Required text', $artifact);
        $this->assertStringContainsString('docs/guide.md matches regex /^Guide$/m', $artifact);
        $prompt = app(PromptBuilder::class)->build($task->fresh());
        $this->assertStringContainsString('## Expected content', $prompt);
        $this->assertStringContainsString('`docs/guide.md` must contain literal text: `Required text`', $prompt);
        $this->assertStringContainsString('`docs/guide.md` must match regex: `/^Guide$/m`', $prompt);
    }

    public function test_it_fails_content_expectations_for_missing_text_nonmatching_and_invalid_regexes(): void
    {
        Storage::fake('local');
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree';
        File::ensureDirectoryExists($worktree.DIRECTORY_SEPARATOR.'docs');
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'guide.md', "Guide\n");
        $task = $this->task(null, $worktree, null, [
            ['file' => 'docs/missing.md', 'text' => 'Required text'],
            ['file' => 'docs/guide.md', 'text' => 'Required text'],
        ], [
            ['file' => 'docs/guide.md', 'pattern' => '/Required/'],
            ['file' => 'docs/missing.md', 'pattern' => '/[/'],
        ]);

        $this->artisan('orchestrator:task-acceptance-check', ['task' => $task->id])->assertFailed();

        $artifact = Storage::disk('local')->get("orchestrator/tasks/{$task->id}/acceptance.md");
        $this->assertStringContainsString('file is missing or unreadable', $artifact);
        $this->assertStringContainsString('literal text is absent', $artifact);
        $this->assertStringContainsString('regex does not match', $artifact);
        $this->assertStringContainsString('## Invalid regex checks', $artifact);
        $this->assertStringContainsString('invalid regex', $artifact);
        $this->assertStringContainsString('Next action: Create missing expected files, satisfy content expectations', $artifact);
    }

    /** @return string */
    private function gitWorktree(): string
    {
        $worktree = $this->root.DIRECTORY_SEPARATOR.'worktree-'.uniqid();
        File::ensureDirectoryExists($worktree.DIRECTORY_SEPARATOR.'docs');
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'README.md', "# Source\n");
        file_put_contents($worktree.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'guide.md', "Guide\n");

        foreach ([
            ['git', 'init', '-b', 'main', $worktree],
            ['git', '-C', $worktree, 'config', 'user.email', 'test@example.com'],
            ['git', '-C', $worktree, 'config', 'user.name', 'Test User'],
            ['git', '-C', $worktree, 'add', 'README.md', 'docs/guide.md'],
            ['git', '-C', $worktree, 'commit', '-m', 'Initial commit'],
        ] as $command) {
            (new Process($command))->mustRun();
        }

        return $worktree;
    }

    /**
     * @param  array<int, string>|null  $expectedFiles
     * @param  array<int, string>|null  $forbiddenFiles
     * @param  array<int, array{file: string, text: string}>|null  $expectedTexts
     * @param  array<int, array{file: string, pattern: string}>|null  $expectedRegexes
     */
    private function task(?array $expectedFiles, ?string $worktree = null, ?array $forbiddenFiles = null, ?array $expectedTexts = null, ?array $expectedRegexes = null): OrchestratorTask
    {
        $project = OrchestratorProject::create([
            'name' => 'project-'.uniqid(),
            'repo_path' => $this->root.DIRECTORY_SEPARATOR.'repo-'.uniqid(),
            'default_branch' => 'main',
        ]);

        return OrchestratorTask::create([
            'project_id' => $project->id,
            'title' => 'Check expected files',
            'worktree_path' => $worktree,
            'expected_files' => $expectedFiles,
            'forbidden_files' => $forbiddenFiles,
            'expected_texts' => $expectedTexts,
            'expected_regexes' => $expectedRegexes,
        ]);
    }
}
