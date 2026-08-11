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
@endsection
