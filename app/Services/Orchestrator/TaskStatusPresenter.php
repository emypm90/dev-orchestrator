<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;

class TaskStatusPresenter
{
    public function needsHumanReview(OrchestratorTask $task): bool
    {
        return $task->status === 'completed' && $task->review_decision === null;
    }

    public function needsAttention(OrchestratorTask $task): bool
    {
        return $this->needsHumanReview($task)
            || in_array($task->status, ['running', 'blocked', 'needs_revision'], true)
            || $task->last_verification_status === 'failed'
            || $task->last_acceptance_status === 'failed';
    }

    public function nextAction(OrchestratorTask $task): string
    {
        return match ($task->status) {
            'archived' => 'No action.',
            'approved' => 'Archive when ready.',
            'needs_revision' => 'Rerun with the recorded revision request.',
            'draft' => 'Prepare task.',
            'prepared' => 'Run task.',
            'blocked' => 'Resolve blocker, then rerun task.',
            'failed' => 'Review run log, then rerun task.',
            'running' => 'Wait or check run log.',
            'completed' => $this->completedNextAction($task),
            default => 'Check task status and artifacts.',
        };
    }

    private function completedNextAction(OrchestratorTask $task): string
    {
        if ($task->last_verification_status === 'failed') {
            return 'Fix verification failure, then rerun.';
        }

        if ($task->last_acceptance_status === 'failed') {
            return 'Fix acceptance failure, then rerun.';
        }

        if ($task->last_verification_status === null) {
            return 'Run verification before review.';
        }

        if ($task->last_acceptance_status === null) {
            return 'Run acceptance check before review.';
        }

        if ($task->review_decision === null) {
            return 'Review artifacts, then approve, reject, or request revision.';
        }

        return 'Review recorded decision.';
    }
}
