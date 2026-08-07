@extends('layouts.app', ['title' => 'Task dashboard', 'heading' => 'Task dashboard'])

@section('content')
    <section class="panel">
        <h2>Status summary</h2>
        <div class="counts">
            @forelse ($statusCounts as $status => $count)
                <div class="count"><strong>{{ $count }}</strong><span>{{ $status }}</span></div>
            @empty
                <p class="muted">No tasks match the current filters.</p>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <h2>Attention queue</h2>
        <div class="counts">
            @foreach ($attentionCounts as $label => $count)
                <div class="count"><strong>{{ $count }}</strong><span>{{ $label }}</span></div>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <form class="filters" method="GET" action="{{ route('tasks.index') }}">
            <label>Project
                <select name="project">
                    <option value="">All projects</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->name }}" @selected(request('project') === $project->name)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach ($statusCounts->keys() as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label><input type="checkbox" name="attention" value="1" @checked(request()->boolean('attention'))> Attention only</label>
            <button type="submit">Filter</button>
            @if (request()->hasAny(['project', 'status', 'attention']))
                <a class="filter-link" href="{{ route('tasks.index') }}">Clear filters</a>
            @endif
        </form>
    </section>

    <section class="panel">
        <h2>Tasks <span class="muted">({{ $tasks->count() }})</span></h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Project</th><th>Title</th><th>Status</th><th>Review</th><th>Verification</th><th>Acceptance</th><th>Updated</th><th>Next action</th></tr></thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr>
                            <td>#{{ $task->id }}</td>
                            <td>{{ $task->project->name }}</td>
                            <td class="title"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                            <td><span class="badge">{{ $task->status }}</span></td>
                            <td>{{ $task->review_decision ?? '-' }}</td>
                            <td>{{ $task->last_verification_status ?? '-' }}</td>
                            <td>{{ $task->last_acceptance_status ?? '-' }}</td>
                            <td title="{{ $task->updated_at->toDateTimeString() }}">{{ $task->updated_at->diffForHumans() }}</td>
                            <td>{{ $presenter->nextAction($task) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted">No matching tasks.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
