<?php

namespace App\Console\Commands;

use App\Models\OrchestratorProject;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class OrchestratorProjectAdd extends Command
{
    protected $signature = 'orchestrator:project-add
        {name : Unique project name}
        {repo_path : Existing local Git repository path}
        {--default-branch=main : Local branch used as the worktree base}
        {--test= : Project test command}
        {--lint= : Project lint command}
        {--rules= : Project-specific rules for OpenCode}';

    protected $description = 'Register a local Git project for orchestration';

    public function handle(): int
    {
        $path = realpath($this->argument('repo_path'));
        if ($path === false || ! is_dir($path)) {
            $this->error('Repository path does not exist.');

            return self::FAILURE;
        }

        $git = new Process(['git', '-C', $path, 'rev-parse', '--is-inside-work-tree']);
        $git->run();
        if (! $git->isSuccessful()) {
            $this->error('Repository path is not a Git worktree.');

            return self::FAILURE;
        }

        $project = OrchestratorProject::create([
            'name' => $this->argument('name'),
            'repo_path' => $path,
            'default_branch' => $this->option('default-branch'),
            'test_command' => $this->option('test'),
            'lint_command' => $this->option('lint'),
            'rules' => $this->option('rules'),
        ]);

        $this->info("Registered project {$project->name} (#{$project->id}).");

        return self::SUCCESS;
    }
}
