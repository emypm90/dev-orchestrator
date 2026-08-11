@extends('layouts.app', ['title' => 'Ticket #'.$ticket->id, 'heading' => 'Detalle operativo'])

@section('content')
    <a class="back-link" href="{{ route('operational-tickets.index') }}">&larr; Volver a tickets operativos</a>
    <section class="panel">
        <p class="eyebrow">Ticket #{{ $ticket->id }} / {{ $ticket->project_name }}</p>
        <h2 class="task-heading">{{ $ticket->title }}</h2>
        <div class="state-row"><span class="badge priority-{{ $ticket->priority }}">{{ \App\Models\OperationalTicket::priorityLabel($ticket->priority) }}</span><span class="badge status-{{ $ticket->status }}">{{ \App\Models\OperationalTicket::statusLabel($ticket->status) }}</span><span class="badge">{{ \App\Models\OperationalTicket::sourceLabel($ticket->source) }}</span></div>
    </section>
    <section class="panel">
        <div class="decision-summary">
            <section class="summary-card"><span class="section-kicker">Solicitante</span><h3>{{ $ticket->requester ?: 'Sin indicar' }}</h3></section>
            <section class="summary-card"><span class="section-kicker">Fecha límite</span><h3>{{ $ticket->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</h3></section>
            <section class="summary-card summary-objective"><span class="section-kicker">Objetivo operativo</span><p>{{ $ticket->objective ?: 'Pendiente de definir durante el triage.' }}</p></section>
            <section class="summary-card summary-next-action"><span class="section-kicker">Próximo paso operativo</span><strong>{{ $ticket->nextOperationalStep() }}</strong></section>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><h2>Pedido original y contexto</h2><p class="panel-copy">Conservado sin convertirlo todavía en una tarea de ejecución.</p></div></div>
        <div class="request-context">{{ $ticket->original_text }}</div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><h2>Triage y actualización</h2><p class="panel-copy">Completá y revisá estos datos antes de pasar el pedido a implementación.</p></div></div>
        <form class="ticket-form" method="POST" action="{{ route('operational-tickets.update', $ticket) }}">
            @csrf
            @method('PATCH')
            <label>Proyecto<input name="project_name" value="{{ old('project_name', $ticket->project_name) }}" required></label>
            <label>Solicitante <span class="muted">(opcional)</span><input name="requester" value="{{ old('requester', $ticket->requester) }}"></label>
            <label>Título<input name="title" value="{{ old('title', $ticket->title) }}" required></label>
            <label>Origen<select name="source" required>@foreach (\App\Models\OperationalTicket::SOURCES as $source)<option value="{{ $source }}" @selected(old('source', $ticket->source) === $source)>{{ \App\Models\OperationalTicket::sourceLabel($source) }}</option>@endforeach</select></label>
            <label>Prioridad<select name="priority" required>@foreach (\App\Models\OperationalTicket::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(old('priority', $ticket->priority) === $priority)>{{ \App\Models\OperationalTicket::priorityLabel($priority) }}</option>@endforeach</select></label>
            <label>Estado<select name="status" required>@foreach (\App\Models\OperationalTicket::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $ticket->status) === $status)>{{ \App\Models\OperationalTicket::statusLabel($status) }}</option>@endforeach</select></label>
            <label>Fecha límite <span class="muted">(opcional)</span><input type="date" name="due_date" value="{{ old('due_date', $ticket->due_date?->format('Y-m-d')) }}"></label>
            <label class="wide-field">Pedido o contexto original<textarea name="original_text" required>{{ old('original_text', $ticket->original_text) }}</textarea></label>
            <label class="wide-field">Objetivo operativo <span class="muted">(opcional)</span><textarea name="objective">{{ old('objective', $ticket->objective) }}</textarea></label>
            <div class="wide-field"><button type="submit">Guardar triage</button></div>
        </form>
    </section>
    <section class="panel">
        <div class="panel-header"><div><h2>Tarea de ejecución</h2><p class="panel-copy">Los tickets crudos deben pasar por triage y quedar como listos antes de iniciar implementación.</p></div></div>
        @if ($ticket->orchestratorTask)
            <p>Este ticket está vinculado a <a href="{{ route('tasks.show', $ticket->orchestratorTask) }}">la tarea de ejecución #{{ $ticket->orchestratorTask->id }}: {{ $ticket->orchestratorTask->title }}</a>.</p>
        @elseif ($ticket->status === 'ready')
            <form method="POST" action="{{ route('operational-tickets.convert', $ticket) }}">
                @csrf
                <button type="submit">Crear tarea de ejecución</button>
            </form>
        @else
            <p class="muted">Completá el triage y cambiá el estado a "Lista" para habilitar la creación de la tarea de ejecución.</p>
        @endif
    </section>
@endsection
