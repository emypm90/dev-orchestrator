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

    public function artifactPath(OrchestratorTask $task, string $file): string
    {
        return "orchestrator/tasks/{$task->id}/{$file}";
    }
}
