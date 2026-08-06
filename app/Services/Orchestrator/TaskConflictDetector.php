<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Symfony\Component\Process\Process;

class TaskConflictDetector
{
    /**
     * @param  iterable<OrchestratorTask>  $tasks
     * @return array<string, array<int, int>>
     */
    public function detect(iterable $tasks): array
    {
        $files = [];

        foreach ($tasks as $task) {
            if ($task->worktree_path === null || ! is_dir($task->worktree_path)) {
                continue;
            }

            foreach ($this->changedFiles($task->worktree_path) as $file) {
                $files[$file][] = $task->id;
            }
        }

        $conflicts = array_filter($files, fn (array $taskIds): bool => count(array_unique($taskIds)) > 1);
        ksort($conflicts);

        return $conflicts;
    }

    /** @return array<int, string> */
    private function changedFiles(string $worktree): array
    {
        return array_values(array_unique([
            ...$this->lines($this->git($worktree, ['diff', '--name-only'])),
            ...$this->lines($this->git($worktree, ['diff', '--cached', '--name-only'])),
            ...$this->lines($this->git($worktree, ['ls-files', '--others', '--exclude-standard'])),
        ]));
    }

    private function git(string $worktree, array $arguments): string
    {
        $process = new Process(['git', '-C', $worktree, ...$arguments]);
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
}
