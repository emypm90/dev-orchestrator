<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class AcceptanceChecker
{
    /**
     * @return array{status: string, path: string, directory: string, found: array<int, string>, missing: array<int, string>, clean: array<int, string>, violated: array<int, string>, touched: array<int, string>}
     */
    public function check(OrchestratorTask $task): array
    {
        $directory = $task->worktree_path !== null && is_dir($task->worktree_path)
            ? $task->worktree_path
            : $task->project->repo_path;
        $expected = $task->expected_files ?? [];
        $forbidden = $task->forbidden_files ?? [];
        $found = [];
        $missing = [];

        foreach ($expected as $file) {
            if (is_file($directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file))) {
                $found[] = $file;
            } else {
                $missing[] = $file;
            }
        }

        $touched = $this->changedFiles($directory);
        $violated = array_values(array_intersect($forbidden, $touched));
        $clean = array_values(array_diff($forbidden, $violated));
        $status = $expected === [] && $forbidden === []
            ? 'skipped'
            : ($missing === [] && $violated === [] ? 'passed' : 'failed');
        $path = "orchestrator/tasks/{$task->id}/acceptance.md";
        Storage::disk('local')->put($path, $this->markdown($task, $status, $directory, $expected, $found, $missing, $forbidden, $clean, $violated, $touched));
        $absolutePath = Storage::disk('local')->path($path);

        $task->update([
            'last_acceptance_status' => $status,
            'last_acceptance_checked_at' => now(),
            'last_acceptance_path' => $absolutePath,
        ]);

        return compact('status', 'directory', 'found', 'missing', 'clean', 'violated', 'touched') + ['path' => $absolutePath];
    }

    /**
     * @param  array<int, string>  $expected
     * @param  array<int, string>  $found
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $forbidden
     * @param  array<int, string>  $clean
     * @param  array<int, string>  $violated
     * @param  array<int, string>  $touched
     */
    private function markdown(OrchestratorTask $task, string $status, string $directory, array $expected, array $found, array $missing, array $forbidden, array $clean, array $violated, array $touched): string
    {
        $nextAction = match ($status) {
            'passed' => 'Continue with human review; this objective file check does not approve the task.',
            'failed' => 'Create missing expected files and revert forbidden file changes, then rerun this acceptance check.',
            default => 'Configure expected files with orchestrator:task-expect-file or forbidden files with orchestrator:task-forbid-file, then rerun this check.',
        };

        return "# Acceptance check for task {$task->id}\n\n"
            ."- Status: {$status}\n"
            .'- Checked: '.now()->toIso8601String()."\n"
            ."- Directory used: {$directory}\n"
            ."- Directory source: ".($directory === $task->worktree_path ? 'task worktree' : 'project repository')."\n"
            ."- Next action: {$nextAction}\n\n"
            ."## Expected files\n".$this->files($expected)."\n"
            ."## Found files\n".$this->files($found)."\n"
            ."## Missing files\n".$this->files($missing)."\n"
            ."## Forbidden files\n".$this->files($forbidden)."\n"
            ."## Clean forbidden files\n".$this->files($clean)."\n"
            ."## Violated forbidden files\n".$this->files($violated)."\n"
            ."## Touched files\n".$this->files($touched)."\n";
    }

    /** @return array<int, string> */
    private function changedFiles(string $directory): array
    {
        return array_values(array_unique([
            ...$this->lines($this->git($directory, ['diff', '--name-only'])),
            ...$this->lines($this->git($directory, ['diff', '--cached', '--name-only'])),
            ...$this->lines($this->git($directory, ['ls-files', '--others', '--exclude-standard'])),
        ]));
    }

    private function git(string $directory, array $arguments): string
    {
        $process = new Process(['git', '-C', $directory, ...$arguments]);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }

    /** @return array<int, string> */
    private function lines(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output))));
    }

    /** @param array<int, string> $files */
    private function files(array $files): string
    {
        return $files === [] ? "None.\n" : implode("\n", array_map(fn (string $file): string => "- `{$file}`", $files))."\n";
    }
}
