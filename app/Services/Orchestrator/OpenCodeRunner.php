<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class OpenCodeRunner
{
    public function isAvailable(): bool
    {
        $process = new Process(['where', 'opencode']);
        $process->run();

        return $process->isSuccessful();
    }

    public function run(OrchestratorTask $task, string $promptPath): int
    {
        $process = $this->start($task, $promptPath);

        return $this->finish($task, $process);
    }

    public function start(OrchestratorTask $task, string $promptPath): Process
    {
        $prompt = file_get_contents($promptPath);
        $process = new Process(['opencode', 'run', '--dir', $task->worktree_path, $prompt], timeout: null);
        $task->update(['status' => 'running', 'started_at' => now()]);
        $process->start();

        return $process;
    }

    public function finish(OrchestratorTask $task, Process $process): int
    {
        $process->wait();

        Storage::disk('local')->put(
            "orchestrator/tasks/{$task->id}/run.log",
            $process->getOutput().$process->getErrorOutput(),
        );

        $exitCode = $process->getExitCode() ?? 1;
        $task->update([
            'status' => $exitCode === 0 ? 'completed' : 'failed',
            'last_exit_code' => $exitCode,
            'finished_at' => now(),
        ]);

        return $exitCode;
    }

    public function recordUnavailable(OrchestratorTask $task): void
    {
        Storage::disk('local')->put(
            "orchestrator/tasks/{$task->id}/run.log",
            "OpenCode CLI was not found on PATH. Install or expose `opencode`, then run this command again.\n",
        );
        $task->update(['status' => 'blocked']);
    }
}
