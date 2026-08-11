<?php

namespace App\Http\Controllers;

use App\Models\OrchestratorProject;
use App\Models\OrchestratorTask;
use App\Services\Orchestrator\ReviewDecisionRecorder;
use App\Services\Orchestrator\TaskArchiver;
use App\Services\Orchestrator\TaskDiffViewer;
use App\Services\Orchestrator\TaskStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskDashboardController extends Controller
{
    private const ARTIFACT_NAMES = [
        'prompt.md',
        'run.log',
        'verification.md',
        'acceptance.md',
        'review.md',
        'decision.md',
        'rerun.md',
        'archive.md',
        'final.patch',
    ];

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
                'revisión humana' => $tasks->filter(fn (OrchestratorTask $task) => $presenter->needsHumanReview($task))->count(),
                'verificación fallida' => $tasks->where('last_verification_status', 'failed')->count(),
                'aceptación fallida' => $tasks->where('last_acceptance_status', 'failed')->count(),
                'requiere revisión' => $tasks->where('status', 'needs_revision')->count(),
                'en ejecución' => $tasks->where('status', 'running')->count(),
                'bloqueada' => $tasks->where('status', 'blocked')->count(),
            ],
            'presenter' => $presenter,
        ]);
    }

    public function show(OrchestratorTask $task, TaskStatusPresenter $presenter)
    {
        $directory = "orchestrator/tasks/{$task->id}";
        $disk = Storage::disk('local');
        $revisions = collect($disk->files($directory))
            ->map(fn (string $path) => basename($path))
            ->filter(fn (string $artifact) => preg_match('/^revision-\d+\.md$/', $artifact) === 1)
            ->sortBy(fn (string $artifact) => (int) preg_replace('/\D/', '', $artifact))
            ->values();
        $artifactNames = collect(self::ARTIFACT_NAMES)
            ->merge($revisions)
            ->map(fn (string $artifact) => [
                'name' => $artifact,
                'exists' => $disk->exists("{$directory}/{$artifact}"),
            ]);
        $artifactsByName = $artifactNames->keyBy('name');

        return view('tasks.show', [
            'task' => $task->load('project'),
            'presenter' => $presenter,
            'artifacts' => $artifactNames,
            'archiveArtifact' => $artifactsByName->get('archive.md'),
            'canArchive' => in_array($task->review_decision, ['approved', 'rejected'], true)
                && $task->archived_at === null
                && $task->status !== 'archived',
        ]);
    }

    public function diff(OrchestratorTask $task, TaskDiffViewer $diffViewer)
    {
        $diff = $diffViewer->collect($task);

        return view('tasks.diff', [
            'task' => $task->load('project'),
            'files' => $diff['files'],
            'warning' => $diff['warning'],
        ]);
    }

    public function approve(Request $request, OrchestratorTask $task, ReviewDecisionRecorder $decisions)
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']], [
            'notes.max' => 'Las notas no pueden superar los :max caracteres.',
        ]);

        $decisions->record($task->loadMissing('project'), 'approved', ($validated['notes'] ?? null) ?: 'No se proporcionaron notas.');

        return redirect()->route('tasks.show', $task)->with('success', "La tarea {$task->id} fue aprobada. Decisión registrada.");
    }

    public function revision(Request $request, OrchestratorTask $task, ReviewDecisionRecorder $decisions)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']], [
            'reason.max' => 'El motivo no puede superar los :max caracteres.',
        ]);

        $decisions->record($task->loadMissing('project'), 'needs_revision', ($validated['reason'] ?? null) ?: 'No se proporcionó un motivo.');

        return redirect()->route('tasks.show', $task)->with('success', "La tarea {$task->id} requiere revisión. Decisión registrada.");
    }

    public function reject(Request $request, OrchestratorTask $task, ReviewDecisionRecorder $decisions)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']], [
            'reason.max' => 'El motivo no puede superar los :max caracteres.',
        ]);

        $decisions->record($task->loadMissing('project'), 'rejected', ($validated['reason'] ?? null) ?: 'No se proporcionó un motivo.');

        return redirect()->route('tasks.show', $task)->with('success', "La tarea {$task->id} fue rechazada. Decisión registrada.");
    }

    public function archive(OrchestratorTask $task, TaskArchiver $archiver)
    {
        if ($task->status === 'archived' || $task->archived_at !== null) {
            return redirect()->route('tasks.show', $task)->with('success', "La tarea {$task->id} ya estaba archivada.");
        }

        if (! in_array($task->review_decision, ['approved', 'rejected'], true)) {
            return redirect()->route('tasks.show', $task)->withErrors([
                'archive' => 'Solo podés archivar una tarea aprobada o rechazada en la revisión humana.',
            ]);
        }

        $archiver->archive($task->loadMissing('project'));

        return redirect()->route('tasks.show', $task)->with('success', "La tarea {$task->id} fue archivada. Se conservaron sus artefactos y su worktree.");
    }

    public function showArtifact(Request $request, OrchestratorTask $task)
    {
        $artifact = $request->string('name')->toString();

        abort_unless($this->isAllowedArtifact($artifact), 404);

        $path = "orchestrator/tasks/{$task->id}/{$artifact}";
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return view('tasks.artifact', [
            'task' => $task->load('project'),
            'artifact' => $artifact,
            'content' => $disk->get($path),
            'size' => $disk->size($path),
            'lastModified' => date('Y-m-d H:i:s', $disk->lastModified($path)),
        ]);
    }

    private function isAllowedArtifact(string $artifact): bool
    {
        return in_array($artifact, self::ARTIFACT_NAMES, true)
            || preg_match('/^revision-\d+\.md$/', $artifact) === 1;
    }
}
