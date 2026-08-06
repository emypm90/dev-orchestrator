<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ReviewCollector
{
    public function collect(OrchestratorTask $task): string
    {
        $worktree = $task->worktree_path;
        $status = $this->git($worktree, ['status', '--short']);
        $trackedStat = $this->git($worktree, ['diff', '--stat']);
        $trackedFiles = $this->lines($this->git($worktree, ['diff', '--name-only']));
        $untrackedFiles = $this->lines($this->git($worktree, ['ls-files', '--others', '--exclude-standard']));
        $files = array_values(array_unique([...$trackedFiles, ...$untrackedFiles]));
        $summary = is_file($worktree.DIRECTORY_SEPARATOR.'TASK_SUMMARY.md')
            ? file_get_contents($worktree.DIRECTORY_SEPARATOR.'TASK_SUMMARY.md')
            : $this->fallbackSummary($task, $status, $trackedStat, $files);

        $review = "# Review for task {$task->id}\n\n"
            ."Status: {$task->status}\n\n## Git status\n```\n{$status}\n```\n"
            ."## Diff stat\n```\n".$this->diffStat($trackedStat, $untrackedFiles)."\n```\n"
            ."## Modified files\n```\n".$this->formatLines($files)."\n```\n"
             ."## Latest verification\n".$this->verification($task)."\n"
             ."## Latest acceptance check\n".$this->acceptance($task)."\n"
            ."## Task summary\n{$summary}\n";
        $path = "orchestrator/tasks/{$task->id}/review.md";
        Storage::disk('local')->put($path, $review);

        return Storage::disk('local')->path($path);
    }

    private function git(string $worktree, array $arguments): string
    {
        $process = new Process(['git', '-C', $worktree, ...$arguments]);
        $process->run();

        return trim($process->getOutput().$process->getErrorOutput());
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output))));
    }

    /**
     * @param  array<int, string>  $untrackedFiles
     */
    private function diffStat(string $trackedStat, array $untrackedFiles): string
    {
        $sections = [];

        if ($trackedStat !== '') {
            $sections[] = $trackedStat;
        }

        if ($untrackedFiles !== []) {
            $sections[] = "Untracked files:\n".$this->formatLines($untrackedFiles);
        }

        return $sections === [] ? 'No file changes detected.' : implode("\n\n", $sections);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function formatLines(array $lines): string
    {
        return $lines === [] ? 'No modified files detected.' : implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $files
     */
    private function fallbackSummary(OrchestratorTask $task, string $status, string $trackedStat, array $files): string
    {
        return "No TASK_SUMMARY.md was provided.\n\n"
            ."### Fallback summary\n"
            ."- Task: {$task->title}\n"
            ."- Current status: {$task->status}\n"
            ."- Changed files detected: ".count($files)."\n"
            ."- Git status present: ".($status === '' ? 'No' : 'Yes')."\n"
            ."- Tracked diff present: ".($trackedStat === '' ? 'No' : 'Yes')."\n";
    }

    private function verification(OrchestratorTask $task): string
    {
        $path = "orchestrator/tasks/{$task->id}/verification.md";

        if (! Storage::disk('local')->exists($path)) {
            return 'No verification artifact found.';
        }

        return '- Status: '.($task->last_verification_status ?? 'recorded')."\n- Artifact: ".Storage::disk('local')->path($path);
    }

    private function acceptance(OrchestratorTask $task): string
    {
        $path = "orchestrator/tasks/{$task->id}/acceptance.md";

        if (! Storage::disk('local')->exists($path)) {
            return 'No acceptance artifact found.';
        }

        return '- Status: '.($task->last_acceptance_status ?? 'recorded')."\n- Artifact: ".Storage::disk('local')->path($path);
    }
}
