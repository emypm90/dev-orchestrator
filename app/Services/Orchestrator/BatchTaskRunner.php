<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class BatchTaskRunner
{
    public function __construct(
        private OpenCodeRunner $openCode,
        private VerificationRunner $verification,
        private ReviewCollector $reviews,
        private AcceptanceChecker $acceptance,
    ) {
    }

    /**
     * @param  array<int, array{task: OrchestratorTask, prompt_path: string}>  $jobs
     * @return array<int, array{status: string, exit_code: ?int, prompt_path: string, run_path: string, verification_path: ?string, review_path: ?string, acceptance_path: ?string, acceptance_status: ?string, next_action: string}>
     */
    public function run(array $jobs, int $concurrency, bool $verify, bool $review, bool $checkAcceptance): array
    {
        $results = [];
        $runPaths = [];

        foreach ($jobs as $job) {
            $task = $job['task'];
            $runPaths[$task->id] = Storage::disk('local')->path("orchestrator/tasks/{$task->id}/run.log");
        }

        if (! $this->openCode->isAvailable()) {
            foreach ($jobs as $job) {
                $task = $job['task'];
                $this->openCode->recordUnavailable($task);
                $results[$task->id] = $this->result($task, $job['prompt_path'], $runPaths[$task->id], 'blocked', null, 'Install or expose opencode, then rerun this task.');
            }

            return $this->completeArtifacts($jobs, $results, $verify, $review, $checkAcceptance);
        }

        $pending = array_values($jobs);
        /** @var array<int, array{task: OrchestratorTask, prompt_path: string, process: Process}> $running */
        $running = [];

        while ($pending !== [] || $running !== []) {
            while (count($running) < $concurrency && $pending !== []) {
                $job = array_shift($pending);
                $task = $job['task'];

                try {
                    $running[$task->id] = [...$job, 'process' => $this->openCode->start($task, $job['prompt_path'])];
                } catch (Throwable $exception) {
                    $task->update(['status' => 'failed', 'last_exit_code' => 1, 'finished_at' => now()]);
                    $results[$task->id] = $this->result($task, $job['prompt_path'], $runPaths[$task->id], 'failed', 1, $exception->getMessage());
                }
            }

            foreach ($running as $taskId => $job) {
                if ($job['process']->isRunning()) {
                    continue;
                }

                $task = $job['task'];
                try {
                    $exitCode = $this->openCode->finish($task, $job['process']);
                    $results[$taskId] = $this->result($task, $job['prompt_path'], $runPaths[$taskId], $exitCode === 0 ? 'completed' : 'failed', $exitCode, $exitCode === 0 ? 'Review and verify this task before archiving.' : 'Inspect run.log, fix the issue, then rerun this task.');
                } catch (Throwable $exception) {
                    $task->update(['status' => 'failed', 'last_exit_code' => 1, 'finished_at' => now()]);
                    $results[$taskId] = $this->result($task, $job['prompt_path'], $runPaths[$taskId], 'failed', 1, $exception->getMessage());
                }

                unset($running[$taskId]);
            }

            if ($running !== []) {
                usleep(100000);
            }
        }

        return $this->completeArtifacts($jobs, $results, $verify, $review, $checkAcceptance);
    }

    /**
     * @param  array<int, array{task: OrchestratorTask, prompt_path: string}>  $jobs
     * @param  array<int, array{status: string, exit_code: ?int, prompt_path: string, run_path: string, verification_path: ?string, review_path: ?string, next_action: string}>  $results
     * @return array<int, array{status: string, exit_code: ?int, prompt_path: string, run_path: string, verification_path: ?string, review_path: ?string, acceptance_path: ?string, acceptance_status: ?string, next_action: string}>
     */
    private function completeArtifacts(array $jobs, array $results, bool $verify, bool $review, bool $checkAcceptance): array
    {
        foreach ($jobs as $job) {
            $task = $job['task']->refresh()->load('project');
            $result = $results[$task->id];

            if ($verify) {
                try {
                    $verification = $this->verification->run($task, false, false);
                    $result['verification_path'] = $verification['path'];
                    if ($verification['status'] !== 'passed') {
                        $result['next_action'] = 'Inspect verification.md before manually reviewing this task.';
                    }
                } catch (Throwable $exception) {
                    $result['next_action'] = 'Verification could not run: '.$exception->getMessage();
                }
            }

            if ($review) {
                try {
                    $result['review_path'] = $this->reviews->collect($task->refresh());
                } catch (Throwable $exception) {
                    $result['next_action'] = 'Review artifact could not be collected: '.$exception->getMessage();
                }
            }

            if ($checkAcceptance) {
                try {
                    $acceptance = $this->acceptance->check($task->refresh());
                    $result['acceptance_path'] = $acceptance['path'];
                    $result['acceptance_status'] = $acceptance['status'];
                    if ($acceptance['status'] !== 'passed') {
                        $result['next_action'] = 'Inspect acceptance.md and resolve missing expected files or forbidden file changes.';
                    }
                } catch (Throwable $exception) {
                    $result['next_action'] = 'Acceptance check could not run: '.$exception->getMessage();
                }
            }

            $results[$task->id] = $result;
        }

        return $results;
    }

    /**
     * @return array{status: string, exit_code: ?int, prompt_path: string, run_path: string, verification_path: ?string, review_path: ?string, acceptance_path: ?string, acceptance_status: ?string, next_action: string}
     */
    private function result(OrchestratorTask $task, string $promptPath, string $runPath, string $status, ?int $exitCode, string $nextAction): array
    {
        return [
            'status' => $status,
            'exit_code' => $exitCode,
            'prompt_path' => $promptPath,
            'run_path' => $runPath,
            'verification_path' => null,
            'review_path' => null,
            'acceptance_path' => null,
            'acceptance_status' => null,
            'next_action' => $nextAction,
        ];
    }
}
