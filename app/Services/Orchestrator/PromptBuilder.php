<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;

class PromptBuilder
{
    public function build(OrchestratorTask $task): string
    {
        $project = $task->project;

        return <<<PROMPT
# Task {$task->id}: {$task->title}

## Context
You are working only in this task's isolated git worktree: {$task->worktree_path}
Autonomy level: {$task->autonomy}

## Project rules
{$project->rules}

## Task description
{$task->description}

## Acceptance criteria
{$task->acceptance_criteria}

## Expected files
{$this->expectedFiles($task)}

## Verification commands
Test: {$project->test_command}
Lint: {$project->lint_command}

## Safety constraints
- Do not commit, stage, push, rebase, reset, or modify git configuration.
- Do not modify files outside this worktree.
- Keep changes focused on this task and preserve existing project conventions.
- Run the relevant test and lint commands when practical; report their results.
- Before finishing, inspect `git diff` and write a concise implementation and verification summary to `TASK_SUMMARY.md` in the worktree.
PROMPT;
    }

    public function save(OrchestratorTask $task): string
    {
        $path = $this->artifactPath($task, 'prompt.md');
        Storage::disk('local')->put($path, $this->build($task));

        return Storage::disk('local')->path($path);
    }

    public function saveRevision(OrchestratorTask $task, int $attempt, ?string $instructions): string
    {
        $path = $this->artifactPath($task, "revision-{$attempt}.md");
        Storage::disk('local')->put($path, $this->buildRevision($task, $instructions));

        return Storage::disk('local')->path($path);
    }

    private function buildRevision(OrchestratorTask $task, ?string $instructions): string
    {
        $decision = $this->artifactContent($task, 'decision.md') ?? 'No decision artifact found.';
        $reviewPath = $this->artifactLocation($task, 'review.md');
        $verificationPath = $this->artifactLocation($task, 'verification.md');

        return $this->build($task)."\n\n## Revision context\n"
            ."The task is being rerun to address prior review feedback. Preserve good existing work and correct only what is needed to satisfy every acceptance criterion.\n\n"
            ."## Latest decision\n{$decision}\n"
            ."## Latest review\n- Status: ".($reviewPath === null ? 'Not available.' : 'Available.')."\n- Artifact: ".($reviewPath ?? 'Not available.')."\n"
            ."## Latest verification\n- Status: ".($task->last_verification_status ?? 'Not recorded')."\n- Artifact: ".($verificationPath ?? 'Not available.')."\n"
            ."## Additional revision instructions\n".(filled($instructions) ? $instructions : 'None provided.')."\n\n"
            ."## Revision safety constraints\n"
            ."- Work only in the current task worktree; do not create or switch worktrees.\n"
            ."- Do not commit, stage, push, rebase, reset, or modify Git configuration.\n"
            ."- Preserve correct existing work and satisfy the original acceptance criteria before finishing.\n";
    }

    private function artifactContent(OrchestratorTask $task, string $file): ?string
    {
        $path = $this->artifactPath($task, $file);

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null;
    }

    private function expectedFiles(OrchestratorTask $task): string
    {
        $files = $task->expected_files ?? [];

        return $files === []
            ? 'No machine-readable expected files are configured.'
            : implode("\n", array_map(fn (string $file): string => "- `{$file}`", $files));
    }

    private function artifactLocation(OrchestratorTask $task, string $file): ?string
    {
        $path = $this->artifactPath($task, $file);

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    public function artifactPath(OrchestratorTask $task, string $file): string
    {
        return "orchestrator/tasks/{$task->id}/{$file}";
    }
}
