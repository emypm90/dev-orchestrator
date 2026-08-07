@extends('layouts.app', ['title' => 'Command center', 'heading' => 'Command center'])

@section('content')
    @php
        $stateClass = fn (?string $value) => 'state-'.str_replace(' ', '_', $value ?: 'empty');
    @endphp

    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span> LOCAL REVIEW FLOW / ONLINE</div>
        <h2>Start with the attention queue.</h2>
        <p>See what needs a human decision, why it needs it, and the safest next artifact to inspect. This dashboard is review-only: it does not run tasks or alter Git.</p>
    </section>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-header">
                <div><h2>Status overview</h2><p class="panel-copy">Current state across the filtered task set.</p></div>
            </div>
            <div class="counts">
                @forelse ($statusCounts as $status => $count)
                    <div class="count"><strong>{{ $count }}</strong><span>{{ $status }}</span></div>
                @empty
                    <p class="muted">No tasks match the current filters.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div><h2>Attention queue</h2><p class="panel-copy">Prioritize review, failed checks, blockers, and revisions.</p></div>
            </div>
            <div class="counts">
                @foreach ($attentionCounts as $label => $count)
                    <div class="count attention-count"><strong>{{ $count }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div><h2>Refine the signal</h2><p class="panel-copy">Use attention-only first, then narrow to a project or status.</p></div>
        </div>
        <form class="filters" method="GET" action="{{ route('tasks.index') }}">
            <label>Project
                <select name="project"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->name }}" @selected(request('project') === $project->name)>{{ $project->name }}</option>@endforeach</select>
            </label>
            <label>Status
                <select name="status"><option value="">All statuses</option>@foreach ($statusCounts->keys() as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
            </label>
            <label class="checkbox-label"><input type="checkbox" name="attention" value="1" @checked(request()->boolean('attention'))> Attention only</label>
            <button type="submit">Apply filters</button>
            @if (request()->hasAny(['project', 'status', 'attention']))<a class="filter-link" href="{{ route('tasks.index') }}">Clear filters</a>@endif
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div><h2>Task telemetry <span class="muted">{{ $tasks->count() }} visible</span></h2><p class="panel-copy">Open a task to read its artifacts and record a safe human decision.</p></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Project</th><th>Task</th><th>Status</th><th>Review</th><th>Verify</th><th>Accept</th><th>Updated</th><th>Recommended next step</th></tr></thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr>
                            <td class="mono muted">#{{ $task->id }}</td><td>{{ $task->project->name }}</td>
                            <td class="title"><a class="task-link" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                            <td><span class="badge status-{{ str_replace(' ', '_', $task->status) }}">{{ $task->status }}</span></td>
                            <td><span class="badge {{ $stateClass($task->review_decision) }}">{{ $task->review_decision ?? 'unreviewed' }}</span></td>
                            <td><span class="badge {{ $stateClass($task->last_verification_status) }}">{{ $task->last_verification_status ?? 'not run' }}</span></td>
                            <td><span class="badge {{ $stateClass($task->last_acceptance_status) }}">{{ $task->last_acceptance_status ?? 'not run' }}</span></td>
                            <td title="{{ $task->updated_at->toDateTimeString() }}">{{ $task->updated_at->diffForHumans() }}</td>
                            <td class="next-action">{{ $presenter->nextAction($task) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted">No matching tasks. Clear filters to see the full local queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
