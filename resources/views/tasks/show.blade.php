@extends('layouts.app', ['title' => 'Task #'.$task->id, 'heading' => 'Task review'])

@section('content')
    @php
        $stateClass = fn (?string $value) => 'state-'.str_replace(' ', '_', $value ?: 'empty');
    @endphp

    <a class="back-link" href="{{ route('tasks.index') }}">&larr; Return to command center</a>

    <section class="panel">
        <p class="eyebrow">Task #{{ $task->id }} / {{ $task->project->name }}</p>
        <h2 class="task-heading">{{ $task->title }}</h2>
        <div class="state-row">
            <span class="badge status-{{ str_replace(' ', '_', $task->status) }}">{{ $task->status }}</span>
            <span class="badge {{ $stateClass($task->review_decision) }}">Review: {{ $task->review_decision ?? 'unreviewed' }}</span>
            <span class="badge {{ $stateClass($task->last_verification_status) }}">Verification: {{ $task->last_verification_status ?? 'not run' }}</span>
            <span class="badge {{ $stateClass($task->last_acceptance_status) }}">Acceptance: {{ $task->last_acceptance_status ?? 'not run' }}</span>
        </div>
    </section>

    <section class="next-action-card">
        <span class="section-kicker">Next recommended action</span>
        <strong>{{ $presenter->nextAction($task) }}</strong>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Task context</h2><p class="panel-copy">Use this metadata to orient the review before opening evidence.</p></div></div>
        <dl class="detail">
            <dt>Project</dt><dd>{{ $task->project->name }}</dd>
            <dt>Branch</dt><dd class="mono">{{ $task->branch_name ?? '-' }}</dd>
            <dt>Worktree path</dt><dd class="mono">{{ $task->worktree_path ?? '-' }}</dd>
            <dt>Review decision</dt><dd>{{ $task->review_decision ?? '-' }}</dd>
            <dt>Reviewed</dt><dd>{{ $task->reviewed_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Review notes</dt><dd>{{ $task->review_notes ?? '-' }}</dd>
            <dt>Verification artifact</dt><dd class="mono">{{ $task->last_verification_path ?? '-' }}</dd>
            <dt>Verified</dt><dd>{{ $task->last_verified_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Acceptance artifact</dt><dd class="mono">{{ $task->last_acceptance_path ?? '-' }}</dd>
            <dt>Acceptance checked</dt><dd>{{ $task->last_acceptance_checked_at?->toDateTimeString() ?? '-' }}</dd>
            <dt>Archive artifact</dt><dd class="mono">{{ $task->archive_path ?? '-' }}</dd>
            <dt>Archived</dt><dd>{{ $task->archived_at?->toDateTimeString() ?? '-' }}</dd>
        </dl>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Review evidence</h2><p class="panel-copy">Open available artifacts first. Muted entries have not been written for this task.</p></div></div>
        <ul class="artifact-grid">
            @foreach ($artifacts as $artifact)
                <li>
                    @if ($artifact['exists'])
                        <a class="artifact-link mono" href="{{ route('tasks.artifacts.show', ['task' => $task, 'artifact' => $artifact['name']]) }}"><span class="artifact-icon">&#9635;</span>{{ $artifact['name'] }}</a>
                    @else
                        <span class="artifact-unavailable mono"><span>&#8211;</span>{{ $artifact['name'] }} (not available)</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Record a review decision</h2><p class="panel-copy">Choose one outcome after reading the evidence. The decision is a human record, not a task command.</p></div></div>
        <p class="safety-notice review-safety"><span class="safety-icon">&#9672;</span><span>These actions record a human decision only. They do not run, archive, or change Git state.</span></p>
        <div class="review-actions">
            <form method="POST" action="{{ route('tasks.approve', $task) }}">
                @csrf
                <h3>Approve</h3><p>Confirm the reviewed work is acceptable. This does not integrate or modify Git.</p>
                <label for="notes">Optional notes<textarea id="notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
                <button type="submit">Record approval</button>
            </form>
            <form class="revision-panel" method="POST" action="{{ route('tasks.revision', $task) }}">
                @csrf
                <h3>Needs revision</h3><p>Record focused feedback for a later CLI-driven revision cycle.</p>
                <label for="revision-reason">Reason<textarea id="revision-reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea></label>
                <button class="revision-button" type="submit">Request revision</button>
            </form>
            <form class="reject-panel" method="POST" action="{{ route('tasks.reject', $task) }}">
                @csrf
                <h3>Reject</h3><p>Record that this task should not be accepted in its current state.</p>
                <label for="reject-reason">Reason<textarea id="reject-reason" name="reason" maxlength="2000">{{ old('reason') }}</textarea></label>
                <button class="reject-button" type="submit">Record rejection</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Acceptance expectations</h2><p class="panel-copy">Configured checks provide evidence, but they do not replace human review.</p></div></div>
        <div class="expectations">
            <section><h3>Expected files ({{ count($task->expected_files ?? []) }})</h3><ul>@forelse ($task->expected_files ?? [] as $file)<li class="mono">{{ $file }}</li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
            <section><h3>Forbidden files ({{ count($task->forbidden_files ?? []) }})</h3><ul>@forelse ($task->forbidden_files ?? [] as $file)<li class="mono">{{ $file }}</li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
            <section><h3>Expected texts ({{ count($task->expected_texts ?? []) }})</h3><ul>@forelse ($task->expected_texts ?? [] as $expectation)<li><span class="mono">{{ $expectation['file'] }}</span>: {{ $expectation['text'] }}</li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
            <section><h3>Expected regexes ({{ count($task->expected_regexes ?? []) }})</h3><ul>@forelse ($task->expected_regexes ?? [] as $expectation)<li><span class="mono">{{ $expectation['file'] }}</span>: <code>{{ $expectation['pattern'] }}</code></li>@empty<li class="muted">None configured.</li>@endforelse</ul></section>
        </div>
    </section>
@endsection
