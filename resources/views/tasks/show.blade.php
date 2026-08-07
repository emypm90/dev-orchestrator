@extends('layouts.app', ['title' => 'Task #'.$task->id, 'heading' => 'Task #'.$task->id])

@section('content')
    <p><a href="{{ route('tasks.index') }}">Back to dashboard</a></p>
    <section class="panel">
        <h2>{{ $task->title }}</h2>
        <dl class="detail">
            <dt>Project</dt><dd>{{ $task->project->name }}</dd>
            <dt>Status</dt><dd><span class="badge">{{ $task->status }}</span></dd>
            <dt>Branch</dt><dd class="mono">{{ $task->branch_name ?? '-' }}</dd>
            <dt>Worktree path</dt><dd class="mono">{{ $task->worktree_path ?? '-' }}</dd>
            <dt>Review decision</dt><dd>{{ $task->review_decision ?? '-' }}</dd>
            <dt>Reviewed</dt><dd>{{ $task->reviewed_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Review notes</dt><dd>{{ $task->review_notes ?? '-' }}</dd>
            <dt>Verification</dt><dd>{{ $task->last_verification_status ?? '-' }}</dd>
            <dt>Verified</dt><dd>{{ $task->last_verified_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Verification artifact</dt><dd class="mono">{{ $task->last_verification_path ?? '-' }}</dd>
            <dt>Acceptance</dt><dd>{{ $task->last_acceptance_status ?? '-' }}</dd>
            <dt>Acceptance checked</dt><dd>{{ $task->last_acceptance_checked_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Acceptance artifact</dt><dd class="mono">{{ $task->last_acceptance_path ?? '-' }}</dd>
            <dt>Archived</dt><dd>{{ $task->archived_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Archive artifact</dt><dd class="mono">{{ $task->archive_path ?? '-' }}</dd>
            <dt>Next recommended action</dt><dd><strong>{{ $presenter->nextAction($task) }}</strong></dd>
        </dl>
    </section>

    <section class="panel">
        <h2>Acceptance expectations</h2>
        <div class="expectations">
            <section><h3>Expected files ({{ count($task->expected_files ?? []) }})</h3><ul>@forelse ($task->expected_files ?? [] as $file)<li class="mono">{{ $file }}</li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
            <section><h3>Forbidden files ({{ count($task->forbidden_files ?? []) }})</h3><ul>@forelse ($task->forbidden_files ?? [] as $file)<li class="mono">{{ $file }}</li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
            <section><h3>Expected texts ({{ count($task->expected_texts ?? []) }})</h3><ul>@forelse ($task->expected_texts ?? [] as $expectation)<li><span class="mono">{{ $expectation['file'] }}</span>: {{ $expectation['text'] }}</li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
            <section><h3>Expected regexes ({{ count($task->expected_regexes ?? []) }})</h3><ul>@forelse ($task->expected_regexes ?? [] as $expectation)<li><span class="mono">{{ $expectation['file'] }}</span>: <code>{{ $expectation['pattern'] }}</code></li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
        </div>
    </section>
@endsection
