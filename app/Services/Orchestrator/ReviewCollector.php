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
        $stat = $this->git($worktree, ['diff', '--stat']);
        $files = $this->git($worktree, ['diff', '--name-only']);
        $summary = is_file($worktree.DIRECTORY_SEPARATOR.'TASK_SUMMARY.md')
            ? file_get_contents($worktree.DIRECTORY_SEPARATOR.'TASK_SUMMARY.md')
            : 'No TASK_SUMMARY.md was provided.';

        $review = "# Review for task {$task->id}\n\n"
            ."Status: {$task->status}\n\n## Git status\n```\n{$status}\n```\n"
            ."## Diff stat\n```\n{$stat}\n```\n"
            ."## Modified files\n```\n{$files}\n```\n"
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
}
