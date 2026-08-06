<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;

class AcceptanceChecker
{
    /**
     * @return array{status: string, path: string, directory: string, found: array<int, string>, missing: array<int, string>}
     */
    public function check(OrchestratorTask $task): array
    {
        $directory = $task->worktree_path !== null && is_dir($task->worktree_path)
            ? $task->worktree_path
            : $task->project->repo_path;
        $expected = $task->expected_files ?? [];
        $found = [];
        $missing = [];

        foreach ($expected as $file) {
            if (is_file($directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file))) {
                $found[] = $file;
            } else {
                $missing[] = $file;
            }
        }

        $status = $expected === [] ? 'skipped' : ($missing === [] ? 'passed' : 'failed');
        $path = "orchestrator/tasks/{$task->id}/acceptance.md";
        Storage::disk('local')->put($path, $this->markdown($task, $status, $directory, $expected, $found, $missing));
        $absolutePath = Storage::disk('local')->path($path);

        $task->update([
            'last_acceptance_status' => $status,
            'last_acceptance_checked_at' => now(),
            'last_acceptance_path' => $absolutePath,
        ]);

        return compact('status', 'directory', 'found', 'missing') + ['path' => $absolutePath];
    }

    /**
     * @param  array<int, string>  $expected
     * @param  array<int, string>  $found
     * @param  array<int, string>  $missing
     */
    private function markdown(OrchestratorTask $task, string $status, string $directory, array $expected, array $found, array $missing): string
    {
        $nextAction = match ($status) {
            'passed' => 'Continue with human review; this objective file check does not approve the task.',
            'failed' => 'Create the missing expected files, then rerun this acceptance check.',
            default => 'Configure expected files with orchestrator:task-expect-file, then rerun this check.',
        };

        return "# Acceptance check for task {$task->id}\n\n"
            ."- Status: {$status}\n"
            .'- Checked: '.now()->toIso8601String()."\n"
            ."- Directory used: {$directory}\n"
            ."- Directory source: ".($directory === $task->worktree_path ? 'task worktree' : 'project repository')."\n"
            ."- Next action: {$nextAction}\n\n"
            ."## Expected files\n".$this->files($expected)."\n"
            ."## Found files\n".$this->files($found)."\n"
            ."## Missing files\n".$this->files($missing)."\n";
    }

    /** @param array<int, string> $files */
    private function files(array $files): string
    {
        return $files === [] ? "None.\n" : implode("\n", array_map(fn (string $file): string => "- `{$file}`", $files))."\n";
    }
}
