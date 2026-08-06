<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class TaskArchiver
{
    public function archive(OrchestratorTask $task): string
    {
        $worktree = $task->worktree_path;
        $hasWorktree = $worktree !== null && is_dir($worktree);
        $snapshot = $hasWorktree ? $this->snapshot($worktree) : $this->missingWorktreeSnapshot($worktree);
        $path = "orchestrator/tasks/{$task->id}/archive.md";

        Storage::disk('local')->put($path, $this->archiveMarkdown($task, $snapshot, $hasWorktree));

        if ($hasWorktree && $snapshot['patch'] !== '') {
            Storage::disk('local')->put("orchestrator/tasks/{$task->id}/final.patch", $snapshot['patch']);
        }

        $task->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archive_path' => Storage::disk('local')->path($path),
            'latest_commit_hash' => $snapshot['latest_commit_hash'],
        ]);

        return Storage::disk('local')->path($path);
    }

    public function removeWorktree(OrchestratorTask $task): void
    {
        if ($task->worktree_removed_at !== null) {
            return;
        }

        if ($task->worktree_path === null || ! is_dir($task->worktree_path)) {
            throw new RuntimeException('Task worktree does not exist, so it was not removed.');
        }

        $process = new Process(['git', '-C', $task->project->repo_path, 'worktree', 'remove', $task->worktree_path]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()));
        }

        $task->update(['worktree_removed_at' => now()]);
        Storage::disk('local')->append(
            "orchestrator/tasks/{$task->id}/archive.md",
            "\nWorktree removal completed after this archive was saved.\n",
        );
    }

    /**
     * @return array{status: string, diff_stat: string, files: array<int, string>, patch: string, latest_commit: string, latest_commit_hash: string}
     */
    private function snapshot(string $worktree): array
    {
        return [
            'status' => $this->git($worktree, ['status', '--short']),
            'diff_stat' => $this->git($worktree, ['diff', '--stat', 'HEAD']),
            'files' => $this->lines($this->git($worktree, ['status', '--short'])),
            'patch' => $this->git($worktree, ['diff', 'HEAD']),
            'latest_commit' => $this->gitOrEmpty($worktree, ['log', '-1', '--pretty=format:%H %s']),
            'latest_commit_hash' => $this->gitOrEmpty($worktree, ['log', '-1', '--pretty=format:%H']),
        ];
    }

    /**
     * @return array{status: string, diff_stat: string, files: array<int, string>, patch: string, latest_commit: string, latest_commit_hash: string}
     */
    private function missingWorktreeSnapshot(?string $worktree): array
    {
        return [
            'status' => 'Worktree unavailable: '.($worktree ?? 'not configured'),
            'diff_stat' => 'Unavailable because the worktree does not exist.',
            'files' => [],
            'patch' => '',
            'latest_commit' => 'Unavailable because the worktree does not exist.',
            'latest_commit_hash' => '',
        ];
    }

    /** @return array<int, string> */
    private function lines(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\r\n|\r|\n/', $output)));
    }

    private function git(string $worktree, array $arguments): string
    {
        $process = new Process(['git', '-C', $worktree, ...$arguments]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()));
        }

        return trim($process->getOutput());
    }

    private function gitOrEmpty(string $worktree, array $arguments): string
    {
        $process = new Process(['git', '-C', $worktree, ...$arguments]);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : '';
    }

    /**
     * @param  array{status: string, diff_stat: string, files: array<int, string>, patch: string, latest_commit: string, latest_commit_hash: string}  $snapshot
     */
    private function archiveMarkdown(OrchestratorTask $task, array $snapshot, bool $hasWorktree): string
    {
        $reviewPath = "orchestrator/tasks/{$task->id}/review.md";
        $review = Storage::disk('local')->exists($reviewPath) ? Storage::disk('local')->path($reviewPath) : 'No review artifact found.';
        $decisionPath = "orchestrator/tasks/{$task->id}/decision.md";
        $decision = Storage::disk('local')->exists($decisionPath) ? Storage::disk('local')->path($decisionPath) : 'No decision artifact found.';
        $verificationPath = "orchestrator/tasks/{$task->id}/verification.md";
        $verification = Storage::disk('local')->exists($verificationPath)
            ? Storage::disk('local')->path($verificationPath)
            : 'No verification artifact found.';
        $files = $snapshot['files'] === [] ? 'No modified files detected.' : implode("\n", $snapshot['files']);
        $removal = $task->worktree_removed_at !== null
            ? 'Worktree was removed before this archive was refreshed.'
            : 'Worktree has not been removed. Use --remove-worktree only after reviewing these artifacts.';

        return "# Archive for task {$task->id}\n\n"
            ."## Task\n"
            ."- Project: {$task->project->name}\n"
            ."- Title: {$task->title}\n"
            ."- Status before archive: {$task->status}\n"
            ."- Branch: ".($task->branch_name ?? 'Not prepared')."\n"
            ."- Worktree: ".($task->worktree_path ?? 'Not configured')."\n"
            ."- Created: {$task->created_at}\n"
            ."- Updated: {$task->updated_at}\n"
            ."- Archived: ".now()."\n"
            ."- Latest commit: ".($snapshot['latest_commit'] ?: 'Unavailable.')."\n\n"
            ."## Git status\n```\n{$snapshot['status']}\n```\n"
            ."## Diff stat\n```\n".($snapshot['diff_stat'] ?: 'No tracked diff detected.')."\n```\n"
            ."## Modified files\n```\n{$files}\n```\n"
            ."## Review artifact\n{$review}\n\n"
            ."## Review decision\n"
            .'- Decision: '.($task->review_decision ?? 'Not recorded')."\n"
            .'- Reviewed: '.($task->reviewed_at?->toDateTimeString() ?? 'Not recorded')."\n"
            .'- Notes: '.($task->review_notes ?? 'Not recorded')."\n"
            ."- Artifact: {$decision}\n\n"
            ."## Latest verification\n"
            .'- Status: '.($task->last_verification_status ?? 'Not recorded')."\n"
            ."- Artifact: {$verification}\n\n"
            ."## Worktree removal\n{$removal}\n"
            .($hasWorktree && $snapshot['patch'] !== '' ? "\nTracked changes were saved to `final.patch`.\n" : "\nNo tracked diff was available for `final.patch`.\n");
    }
}
