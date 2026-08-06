<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class VerificationRunner
{
    /**
     * @return array{path: string, status: string, executed: int}
     */
    public function run(OrchestratorTask $task, bool $testOnly, bool $lintOnly): array
    {
        $commands = $this->commands($task, $testOnly, $lintOnly);
        $cwd = $this->workingDirectory($task);
        $usedWorktree = $task->worktree_path !== null && is_dir($task->worktree_path);

        if ($commands !== [] && ! is_dir($cwd)) {
            throw new RuntimeException("Verification directory does not exist: {$cwd}");
        }

        $startedAt = now();
        $results = array_map(fn (array $command): array => $this->execute($command, $cwd), $commands);
        $status = $this->status($results);
        $path = "orchestrator/tasks/{$task->id}/verification.md";

        Storage::disk('local')->put($path, $this->markdown($task, $cwd, $usedWorktree, $startedAt, now(), $results, $status));
        $task->update([
            'last_verification_status' => $status,
            'last_verified_at' => now(),
            'last_verification_path' => Storage::disk('local')->path($path),
        ]);

        return [
            'path' => Storage::disk('local')->path($path),
            'status' => $status,
            'executed' => count($results),
        ];
    }

    /**
     * @return array<int, array{type: string, command: string}>
     */
    private function commands(OrchestratorTask $task, bool $testOnly, bool $lintOnly): array
    {
        $project = $task->project;
        $includeTest = ! $lintOnly || $testOnly;
        $includeLint = ! $testOnly || $lintOnly;
        $commands = [];

        if ($includeTest && filled($project->test_command)) {
            $commands[] = ['type' => 'test', 'command' => $project->test_command];
        }

        if ($includeLint && filled($project->lint_command)) {
            $commands[] = ['type' => 'lint', 'command' => $project->lint_command];
        }

        return $commands;
    }

    private function workingDirectory(OrchestratorTask $task): string
    {
        return $task->worktree_path !== null && is_dir($task->worktree_path)
            ? $task->worktree_path
            : $task->project->repo_path;
    }

    /**
     * @param  array{type: string, command: string}  $command
     * @return array{type: string, command: string, exit_code: ?int, output: string, duration: float, started_at: string, finished_at: string}
     */
    private function execute(array $command, string $cwd): array
    {
        $startedAt = now();
        $start = microtime(true);
        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $command['command'],
        ], $cwd);
        $process->run();

        return [
            ...$command,
            'exit_code' => $process->getExitCode(),
            'output' => trim($process->getOutput().$process->getErrorOutput()),
            'duration' => microtime(true) - $start,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{exit_code: ?int}>  $results
     */
    private function status(array $results): string
    {
        if ($results === []) {
            return 'skipped';
        }

        $passed = count(array_filter($results, fn (array $result): bool => $result['exit_code'] === 0));

        return match ($passed) {
            count($results) => 'passed',
            0 => 'failed',
            default => 'partial',
        };
    }

    /**
     * @param  array<int, array{type: string, command: string, exit_code: ?int, output: string, duration: float, started_at: string, finished_at: string}>  $results
     */
    private function markdown(OrchestratorTask $task, string $cwd, bool $usedWorktree, $startedAt, $finishedAt, array $results, string $status): string
    {
        $markdown = "# Verification for task {$task->id}\n\n"
            ."- Status: {$status}\n"
            ."- Directory: {$cwd}\n"
            .'- Source: '.($usedWorktree ? 'Task worktree.' : 'Project repository (task worktree unavailable).')."\n"
            ."- Started: {$startedAt->toIso8601String()}\n"
            ."- Finished: {$finishedAt->toIso8601String()}\n";

        if ($results === []) {
            return $markdown."\nNo configured test or lint command matched the selected verification options.\n";
        }

        foreach ($results as $result) {
            $output = $result['output'] === '' ? 'No output.' : $result['output'];
            $markdown .= "\n## {$result['type']}\n"
                ."- Command: `{$result['command']}`\n"
                ."- Exit code: {$result['exit_code']}\n"
                .'- Duration: '.number_format($result['duration'], 3)." seconds\n"
                ."- Started: {$result['started_at']}\n"
                ."- Finished: {$result['finished_at']}\n"
                ."\n### Output\n```\n{$output}\n```\n";
        }

        return $markdown;
    }
}
