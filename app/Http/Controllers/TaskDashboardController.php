<?php

namespace App\Http\Controllers;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\TaskStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TaskDashboardController extends Controller
{
    public function index(Request $request, TaskStatusPresenter $presenter)
    {
        $query = OrchestratorTask::with('project')->orderByDesc('updated_at');

        if ($project = $request->string('project')->trim()->toString()) {
            $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->where('name', $project));
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($request->boolean('attention')) {
            $query->where(function (Builder $attentionQuery): void {
                $attentionQuery->whereIn('status', ['running', 'blocked', 'needs_revision'])
                    ->orWhere('last_verification_status', 'failed')
                    ->orWhere('last_acceptance_status', 'failed')
                    ->orWhere(fn (Builder $reviewQuery) => $reviewQuery->where('status', 'completed')->whereNull('review_decision'));
            });
        }

        $tasks = $query->get();

        return view('tasks.index', [
            'tasks' => $tasks,
            'projects' => OrchestratorProject::orderBy('name')->get(),
            'statusCounts' => $tasks->countBy('status')->sortKeys(),
            'attentionCounts' => [
                'human review' => $tasks->filter(fn (OrchestratorTask $task) => $presenter->needsHumanReview($task))->count(),
                'failed verification' => $tasks->where('last_verification_status', 'failed')->count(),
                'failed acceptance' => $tasks->where('last_acceptance_status', 'failed')->count(),
                'needs revision' => $tasks->where('status', 'needs_revision')->count(),
                'running' => $tasks->where('status', 'running')->count(),
                'blocked' => $tasks->where('status', 'blocked')->count(),
            ],
            'presenter' => $presenter,
        ]);
    }

    public function show(OrchestratorTask $task, TaskStatusPresenter $presenter)
    {
        return view('tasks.show', [
            'task' => $task->load('project'),
            'presenter' => $presenter,
        ]);
    }
}
