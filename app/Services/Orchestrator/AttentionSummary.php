<?php

namespace App\Services\Orchestrator;

use App\Models\OperationalTicket;
use App\Models\OrchestratorTask;
use Illuminate\Database\Eloquent\Builder;

class AttentionSummary
{
    public function operationalTicketQuery(): Builder
    {
        return OperationalTicket::query()->where(function (Builder $query): void {
            $query->whereIn('status', ['inbox', 'triage', 'needs_attention', 'ready'])
                ->orWhere('priority', 'urgent')
                ->orWhere(fn (Builder $dueQuery) => $dueQuery->whereNotNull('due_date')->whereDate('due_date', '<=', today()));
        });
    }

    public function executionTaskQuery(): Builder
    {
        return OrchestratorTask::query()->where(function (Builder $query): void {
            $query->whereIn('status', ['running', 'blocked', 'failed', 'needs_revision'])
                ->orWhere('last_verification_status', 'failed')
                ->orWhere('last_acceptance_status', 'failed')
                ->orWhere(fn (Builder $reviewQuery) => $reviewQuery->where('status', 'completed')->whereNull('review_decision'));
        });
    }

    public function forDashboard(): array
    {
        return [
            'operationalTickets' => [
                'count' => $this->operationalTicketQuery()->count(),
                'triage' => OperationalTicket::query()->whereIn('status', ['inbox', 'triage'])->count(),
                'ready' => OperationalTicket::query()->where('status', 'ready')->count(),
                'needsAttention' => OperationalTicket::query()->where('status', 'needs_attention')->count(),
                'urgent' => OperationalTicket::query()->where('priority', 'urgent')->count(),
                'due' => OperationalTicket::query()->whereNotNull('due_date')->whereDate('due_date', '<=', today())->count(),
                'items' => $this->operationalTicketQuery()->orderByDesc('updated_at')->limit(5)->get(),
            ],
            'executionTasks' => [
                'count' => $this->executionTaskQuery()->count(),
                'humanReview' => OrchestratorTask::query()->where('status', 'completed')->whereNull('review_decision')->count(),
                'failed' => OrchestratorTask::query()->where('status', 'failed')->count(),
                'verificationFailed' => OrchestratorTask::query()->where('last_verification_status', 'failed')->count(),
                'acceptanceFailed' => OrchestratorTask::query()->where('last_acceptance_status', 'failed')->count(),
                'needsRevision' => OrchestratorTask::query()->where('status', 'needs_revision')->count(),
                'blocked' => OrchestratorTask::query()->where('status', 'blocked')->count(),
                'items' => $this->executionTaskQuery()->with('project')->orderByDesc('updated_at')->limit(5)->get(),
            ],
        ];
    }
}
