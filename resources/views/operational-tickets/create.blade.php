@extends('layouts.app', ['title' => 'Cargar pedido', 'heading' => 'Nuevo ticket operativo'])

@section('content')
    <a class="back-link" href="{{ route('operational-tickets.index') }}">&larr; Volver a tickets operativos</a>
    <section class="panel">
        <div class="panel-header"><div><h2>Cargá el pedido tal como llegó</h2><p class="panel-copy">El intake manual conserva contexto para triage. No crea ni ejecuta una tarea de desarrollo.</p></div></div>
        <form class="ticket-form" method="POST" action="{{ route('operational-tickets.store') }}">
            @csrf
            <label>Proyecto<input name="project_name" value="{{ old('project_name') }}" required autofocus></label>
            <label>Solicitante <span class="muted">(opcional)</span><input name="requester" value="{{ old('requester') }}"></label>
            <label>Título<input name="title" value="{{ old('title') }}" required></label>
            <label>Origen<select name="source" required>@foreach (\App\Models\OperationalTicket::SOURCES as $source)<option value="{{ $source }}" @selected(old('source', 'manual') === $source)>{{ \App\Models\OperationalTicket::sourceLabel($source) }}</option>@endforeach</select></label>
            <label>Prioridad<select name="priority" required>@foreach (\App\Models\OperationalTicket::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ \App\Models\OperationalTicket::priorityLabel($priority) }}</option>@endforeach</select></label>
            <label>Estado inicial<select name="status" required>@foreach (\App\Models\OperationalTicket::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', 'inbox') === $status)>{{ \App\Models\OperationalTicket::statusLabel($status) }}</option>@endforeach</select></label>
            <label>Fecha límite <span class="muted">(opcional)</span><input type="date" name="due_date" value="{{ old('due_date') }}"></label>
            <label class="wide-field">Pedido o contexto original<textarea name="original_text" required>{{ old('original_text') }}</textarea></label>
            <label class="wide-field">Objetivo operativo <span class="muted">(opcional)</span><textarea name="objective">{{ old('objective') }}</textarea></label>
            <div class="wide-field"><button type="submit">Registrar ticket en bandeja</button></div>
        </form>
    </section>
@endsection
