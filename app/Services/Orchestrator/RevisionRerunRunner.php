<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RevisionRerunRunner
{
    public function __construct(
        private PromptBuilder $prompts,
        private OpenCodeRunner $openCode,
        private VerificationRunner $verification,
        private ReviewCollector $reviews,
        private AcceptanceChecker $acceptance,
    ) {
    }

    /**
     * @return array{exit_code: int, prompt_path: string, verification_path: ?string, review_path: ?string, acceptance_path: ?string, acceptance_status: ?string, rerun_path: string}
     */
    public function run(OrchestratorTask $task, ?string $instructions, bool $verify, bool $review, bool $checkAcceptance): array
    {
        if ($task->worktree_path === null || ! is_dir($task->worktree_path)) {
            throw new RuntimeException('Task must have an existing worktree before it can be rerun. Prepare it first, or create a child task for new work.');
        }

        $attempt = $this->nextAttempt($task);
        $startedAt = now();
        $previousDecision = $this->artifact('decision.md', $task) ?? 'No previous decision artifact found.';

        // A new agent attempt invalidates the previous human decision until review happens again.
        $task->update([
            'review_decision' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ]);

        $promptPath = $this->prompts->saveRevision($task->refresh(), $attempt, $instructions);
        $verificationPath = null;
        $reviewPath = null;
        $acceptancePath = null;
        $acceptanceStatus = null;

        if (! $this->openCode->isAvailable()) {
            $this->openCode->recordUnavailable($task);

            return $this->result($task, $attempt, $startedAt, $instructions, $previousDecision, 1, $promptPath, $verificationPath, $reviewPath, $acceptancePath, $acceptanceStatus);
        }

        try {
            $exitCode = $this->openCode->run($task, $promptPath);

            if ($verify) {
                $verificationPath = $this->verification->run($task->refresh(), false, false)['path'];
            }

            if ($review) {
                $reviewPath = $this->reviews->collect($task->refresh());
            }
            if ($checkAcceptance) {
                $acceptance = $this->acceptance->check($task->refresh());
                $acceptancePath = $acceptance['path'];
                $acceptanceStatus = $acceptance['status'];
            }
        } catch (Throwable $exception) {
            $this->writeRerun($task, $attempt, $startedAt, $instructions, $previousDecision, 1, $promptPath, $verificationPath, $reviewPath, $acceptancePath, $acceptanceStatus, $exception->getMessage());

            throw $exception;
        }

        return $this->result($task, $attempt, $startedAt, $instructions, $previousDecision, $exitCode, $promptPath, $verificationPath, $reviewPath, $acceptancePath, $acceptanceStatus);
    }

    /**
     * @return array{exit_code: int, prompt_path: string, verification_path: ?string, review_path: ?string, acceptance_path: ?string, acceptance_status: ?string, rerun_path: string}
     */
    private function result(OrchestratorTask $task, int $attempt, $startedAt, ?string $instructions, string $previousDecision, int $exitCode, string $promptPath, ?string $verificationPath, ?string $reviewPath, ?string $acceptancePath, ?string $acceptanceStatus): array
    {
        $rerunPath = $this->writeRerun($task, $attempt, $startedAt, $instructions, $previousDecision, $exitCode, $promptPath, $verificationPath, $reviewPath, $acceptancePath, $acceptanceStatus);

        return [
            'exit_code' => $exitCode,
            'prompt_path' => $promptPath,
            'verification_path' => $verificationPath,
            'review_path' => $reviewPath,
            'acceptance_path' => $acceptancePath,
            'acceptance_status' => $acceptanceStatus,
            'rerun_path' => $rerunPath,
        ];
    }

    private function nextAttempt(OrchestratorTask $task): int
    {
        $files = Storage::disk('local')->allFiles("orchestrator/tasks/{$task->id}");
        $attempts = array_map(
            fn (string $file): int => (int) preg_replace('/.*revision-(\d+)\.md$/', '$1', $file),
            array_filter($files, fn (string $file): bool => preg_match('/revision-\d+\.md$/', $file) === 1),
        );

        return ($attempts === [] ? 0 : max($attempts)) + 1;
    }

    private function artifact(string $file, OrchestratorTask $task): ?string
    {
        $path = "orchestrator/tasks/{$task->id}/{$file}";

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null;
    }

    private function writeRerun(OrchestratorTask $task, int $attempt, $startedAt, ?string $instructions, string $previousDecision, int $exitCode, string $promptPath, ?string $verificationPath, ?string $reviewPath, ?string $acceptancePath, ?string $acceptanceStatus, ?string $error = null): string
    {
        $path = "orchestrator/tasks/{$task->id}/rerun.md";
        $nextAction = $exitCode === 0
            ? 'Review the rerun artifacts, then approve, reject, or request another revision.'
            : 'Inspect the run log and rerun artifact, resolve the failure, then rerun the task again.';
        $markdown = "\n## Rerun attempt {$attempt}\n"
            ."- Started: {$startedAt->toIso8601String()}\n"
            .'- Finished: '.now()->toIso8601String()."\n"
            .'- Instructions: '.(filled($instructions) ? $instructions : 'None provided.')."\n"
            ."- Exit status: {$exitCode}\n"
            ."- Prompt: {$promptPath}\n"
            .'- Verification: '.($verificationPath ?? 'Not requested or not completed.')."\n"
            .'- Review: '.($reviewPath ?? 'Not requested or not completed.')."\n"
            .'- Acceptance: '.($acceptancePath === null ? 'Not requested or not completed.' : "{$acceptanceStatus} ({$acceptancePath})")."\n"
            .'- Next action: '.$nextAction."\n"
            .($error === null ? '' : "- Error: {$error}\n")
            ."\n### Previous decision\n{$previousDecision}\n";

        Storage::disk('local')->append($path, $markdown);

        return Storage::disk('local')->path($path);
    }
}
