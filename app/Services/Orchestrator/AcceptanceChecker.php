<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class AcceptanceChecker
{
    /**
     * @return array{status: string, path: string, directory: string, found: array<int, string>, missing: array<int, string>, clean: array<int, string>, violated: array<int, string>, touched: array<int, string>, content_configured: array<int, string>, content_passed: array<int, string>, content_failed: array<int, string>, invalid_regexes: array<int, string>}
     */
    public function check(OrchestratorTask $task): array
    {
        $directory = $task->worktree_path !== null && is_dir($task->worktree_path)
            ? $task->worktree_path
            : $task->project->repo_path;
        $expected = $task->expected_files ?? [];
        $forbidden = $task->forbidden_files ?? [];
        $expectedTexts = $task->expected_texts ?? [];
        $expectedRegexes = $task->expected_regexes ?? [];
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
        [$contentConfigured, $contentPassed, $contentFailed, $invalidRegexes] = $this->contentChecks($directory, $expectedTexts, $expectedRegexes);
        $status = $expected === [] && $forbidden === [] && $contentConfigured === []
            ? 'skipped'
            : ($missing === [] && $violated === [] && $contentFailed === [] ? 'passed' : 'failed');
        $path = "orchestrator/tasks/{$task->id}/acceptance.md";
        Storage::disk('local')->put($path, $this->markdown($task, $status, $directory, $expected, $found, $missing, $forbidden, $clean, $violated, $touched, $contentConfigured, $contentPassed, $contentFailed, $invalidRegexes));
        $absolutePath = Storage::disk('local')->path($path);

        $task->update([
            'last_acceptance_status' => $status,
            'last_acceptance_checked_at' => now(),
            'last_acceptance_path' => $absolutePath,
        ]);

        return compact('status', 'directory', 'found', 'missing', 'clean', 'violated', 'touched') + [
            'path' => $absolutePath,
            'content_configured' => $contentConfigured,
            'content_passed' => $contentPassed,
            'content_failed' => $contentFailed,
            'invalid_regexes' => $invalidRegexes,
        ];
    }

    /**
     * @param  array<int, string>  $expected
     * @param  array<int, string>  $found
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $forbidden
     * @param  array<int, string>  $clean
     * @param  array<int, string>  $violated
     * @param  array<int, string>  $touched
     * @param  array<int, string>  $contentConfigured
     * @param  array<int, string>  $contentPassed
     * @param  array<int, string>  $contentFailed
     * @param  array<int, string>  $invalidRegexes
     */
    private function markdown(OrchestratorTask $task, string $status, string $directory, array $expected, array $found, array $missing, array $forbidden, array $clean, array $violated, array $touched, array $contentConfigured, array $contentPassed, array $contentFailed, array $invalidRegexes): string
    {
        $nextAction = match ($status) {
            'passed' => 'Continue with human review; this objective file check does not approve the task.',
            'failed' => 'Create missing expected files, satisfy content expectations, and revert forbidden file changes, then rerun this acceptance check.',
            default => 'Configure expected files, forbidden files, expected text, or expected regex checks, then rerun this check.',
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
            ."## Touched files\n".$this->files($touched)."\n"
            ."## Configured content checks\n".$this->files($contentConfigured)."\n"
            ."## Passed content checks\n".$this->files($contentPassed)."\n"
            ."## Failed content checks\n".$this->files($contentFailed)."\n"
            ."## Invalid regex checks\n".$this->files($invalidRegexes)."\n";
    }

    /**
     * @param  array<int, array{file: string, text: string}>  $texts
     * @param  array<int, array{file: string, pattern: string}>  $regexes
     * @return array{array<int, string>, array<int, string>, array<int, string>, array<int, string>}
     */
    private function contentChecks(string $directory, array $texts, array $regexes): array
    {
        $configured = [];
        $passed = [];
        $failed = [];
        $invalidRegexes = [];

        foreach ($texts as $expectation) {
            $summary = "{$expectation['file']} contains literal text {$expectation['text']}";
            $configured[] = $summary;
            $content = $this->fileContent($directory, $expectation['file']);

            if ($content === null) {
                $failed[] = "{$summary} (file is missing or unreadable)";
            } elseif (str_contains($content, $expectation['text'])) {
                $passed[] = $summary;
            } else {
                $failed[] = "{$summary} (literal text is absent)";
            }
        }

        foreach ($regexes as $expectation) {
            $summary = "{$expectation['file']} matches regex {$expectation['pattern']}";
            $configured[] = $summary;

            if (@preg_match($expectation['pattern'], '') === false) {
                $failed[] = "{$summary} (invalid regex)";
                $invalidRegexes[] = $summary;

                continue;
            }

            $content = $this->fileContent($directory, $expectation['file']);

            if ($content === null) {
                $failed[] = "{$summary} (file is missing or unreadable)";

                continue;
            }

            if (preg_match($expectation['pattern'], $content) === 1) {
                $passed[] = $summary;
            } else {
                $failed[] = "{$summary} (regex does not match)";
            }
        }

        return [$configured, $passed, $failed, $invalidRegexes];
    }

    private function fileContent(string $directory, string $file): ?string
    {
        $path = $directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
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
