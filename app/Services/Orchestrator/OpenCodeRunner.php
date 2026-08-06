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
        $process = new Process(
            ['opencode', 'run', '--dir', $task->worktree_path, $prompt],
            env: $this->nestedOpenCodeEnvironment(),
            timeout: null,
        );
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

    /**
     * Avoid leaking the parent OpenCode desktop/client session into nested CLI runs.
     *
     * The inherited OPENCODE_CLIENT and server credentials can make `opencode run`
     * try to reuse an unavailable parent session, which fails with "Session not found".
     * Each task run should start as an isolated CLI session in its task worktree.
     *
     * @return array<string, false>
     */
    private function nestedOpenCodeEnvironment(): array
    {
        return [
            'OPENCODE_CLIENT' => false,
            'OPENCODE_SERVER_USERNAME' => false,
            'OPENCODE_SERVER_PASSWORD' => false,
        ];
    }
}
