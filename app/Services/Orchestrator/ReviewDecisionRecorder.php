<?php

namespace App\Services\Orchestrator;

use App\Models\OrchestratorTask;
use Illuminate\Support\Facades\Storage;

class ReviewDecisionRecorder
{
    public function record(OrchestratorTask $task, string $decision, string $notes): string
    {
        $reviewedAt = now();
        $status = match ($decision) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'needs_revision' => 'needs_revision',
        };

        $task->update([
            'review_decision' => $decision,
            'reviewed_at' => $reviewedAt,
            'review_notes' => $notes,
            'status' => $status,
        ]);

        $decisionPath = "orchestrator/tasks/{$task->id}/decision.md";
        $reviewPath = "orchestrator/tasks/{$task->id}/review.md";
        $review = Storage::disk('local')->exists($reviewPath)
            ? Storage::disk('local')->path($reviewPath)
            : 'No review artifact found.';
        $markdown = "# Review decision for task {$task->id}\n\n"
            ."- Decision: {$decision}\n"
            ."- Timestamp: {$reviewedAt->toIso8601String()}\n"
            ."- Project: {$task->project->name}\n"
            ."- Task: {$task->title}\n"
            ."- Current worktree: ".($task->worktree_path ?? 'Not configured')."\n"
            ."- Latest verification status: ".($task->last_verification_status ?? 'Not recorded')."\n"
            ."- Review artifact: {$review}\n\n"
            ."## Notes\n{$notes}\n";
        Storage::disk('local')->put($decisionPath, $markdown);

        return Storage::disk('local')->path($decisionPath);
    }
}
