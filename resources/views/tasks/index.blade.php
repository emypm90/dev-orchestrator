@extends('layouts.app', ['title' => 'Centro de control', 'heading' => 'Centro de control'])

@section('content')
    @php
        $stateClass = fn (?string $value) => 'state-'.str_replace(' ', '_', $value ?: 'empty');
    @endphp

    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span> FLUJO DE REVISIÓN LOCAL / ACTIVO</div>
        <h2>Empezá por la cola de atención.</h2>
        <p>Identificá qué necesita una decisión humana, por qué la necesita y cuál es el siguiente artefacto más seguro para inspeccionar. Este panel es solo de revisión: no ejecuta tareas ni modifica Git.</p>
    </section>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-header">
                <div><h2>Resumen de estados</h2><p class="panel-copy">Estado actual del conjunto de tareas filtrado.</p></div>
            </div>
            <div class="counts">
                @forelse ($statusCounts as $status => $count)
                    <div class="count"><strong>{{ $count }}</strong><span>{{ $presenter->label($status) }}</span></div>
                @empty
                    <p class="muted">No hay tareas que coincidan con los filtros actuales.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div><h2>Cola de atención</h2><p class="panel-copy">Priorizá revisiones, comprobaciones fallidas, bloqueos y revisiones solicitadas.</p></div>
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
            <div><h2>Refiná la vista</h2><p class="panel-copy">Usá primero "solo atención" y después acotá por proyecto o estado.</p></div>
        </div>
        <form class="filters" method="GET" action="{{ route('tasks.index') }}">
            <label>Proyecto
                <select name="project"><option value="">Todos los proyectos</option>@foreach ($projects as $project)<option value="{{ $project->name }}" @selected(request('project') === $project->name)>{{ $project->name }}</option>@endforeach</select>
            </label>
            <label>Estado
                <select name="status"><option value="">Todos los estados</option>@foreach ($statusCounts->keys() as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $presenter->label($status) }}</option>@endforeach</select>
            </label>
            <label class="checkbox-label"><input type="checkbox" name="attention" value="1" @checked(request()->boolean('attention'))> Solo atención</label>
            <button type="submit">Aplicar filtros</button>
            @if (request()->hasAny(['project', 'status', 'attention']))<a class="filter-link" href="{{ route('tasks.index') }}">Limpiar filtros</a>@endif
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div><h2>Seguimiento de tareas <span class="muted">{{ $tasks->count() }} visibles</span></h2><p class="panel-copy">Abrí una tarea para leer sus artefactos y registrar una decisión humana segura.</p></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Proyecto</th><th>Tarea</th><th>Estado</th><th>Revisión</th><th>Verificación</th><th>Aceptación</th><th>Actualizada</th><th>Próximo paso recomendado</th></tr></thead>
                <tbody>
                    @forelse ($tasks as $task)
                        <tr>
                            <td class="mono muted">#{{ $task->id }}</td><td>{{ $task->project->name }}</td>
                            <td class="title"><a class="task-link" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                            <td><span class="badge status-{{ str_replace(' ', '_', $task->status) }}">{{ $presenter->label($task->status) }}</span></td>
                            <td><span class="badge {{ $stateClass($task->review_decision) }}">{{ $presenter->label($task->review_decision, 'Sin revisar') }}</span></td>
                            <td><span class="badge {{ $stateClass($task->last_verification_status) }}">{{ $presenter->label($task->last_verification_status, 'Sin ejecutar') }}</span></td>
                            <td><span class="badge {{ $stateClass($task->last_acceptance_status) }}">{{ $presenter->label($task->last_acceptance_status, 'Sin ejecutar') }}</span></td>
                            <td title="{{ $task->updated_at->toDateTimeString() }}">{{ $task->updated_at->locale('es')->diffForHumans() }}</td>
                            <td class="next-action">{{ $presenter->nextAction($task, 'es') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted">No hay tareas coincidentes. Limpiá los filtros para ver la cola local completa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
