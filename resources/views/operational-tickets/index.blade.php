@extends('layouts.app', ['title' => 'Tickets operativos', 'heading' => 'Tickets operativos'])

@section('content')
    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span> OPERACIÓN DIARIA / INTAKE MANUAL</div>
        <h2>Primero entendé el pedido; después decidí cómo ejecutarlo.</h2>
        <p>Esta bandeja conserva el contexto operativo antes de crear una tarea de desarrollo. Todavía no ejecuta trabajo ni modifica Git.</p>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Estado de la operación</h2><p class="panel-copy">Los tickets visibles se agrupan por su estado operativo actual.</p></div><a class="button-link" href="{{ route('operational-tickets.create') }}">Cargar pedido manual</a></div>
        <div class="counts">
            @forelse ($statusCounts as $status => $count)
                <div class="count"><strong>{{ $count }}</strong><span>{{ \App\Models\OperationalTicket::statusLabel($status) }}</span></div>
            @empty
                <p class="muted">Todavía no hay pedidos cargados.</p>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Refiná la vista</h2><p class="panel-copy">Priorizá atención y luego acotá por estado u origen.</p></div></div>
        <form class="filters" method="GET" action="{{ route('operational-tickets.index') }}">
            <label>Estado<select name="status"><option value="">Todos los estados</option>@foreach (\App\Models\OperationalTicket::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\OperationalTicket::statusLabel($status) }}</option>@endforeach</select></label>
            <label>Origen<select name="source"><option value="">Todos los orígenes</option>@foreach (\App\Models\OperationalTicket::SOURCES as $source)<option value="{{ $source }}" @selected(request('source') === $source)>{{ \App\Models\OperationalTicket::sourceLabel($source) }}</option>@endforeach</select></label>
            <label class="checkbox-label"><input type="checkbox" name="attention" value="1" @checked(request()->boolean('attention'))> Solo atención</label>
            <button type="submit">Aplicar filtros</button>
            @if (request()->hasAny(['status', 'source', 'attention']))<a class="filter-link" href="{{ route('operational-tickets.index') }}">Limpiar filtros</a>@endif
        </form>
    </section>

    <section class="panel">
        <div class="panel-header"><div><h2>Bandeja operativa <span class="muted">{{ $tickets->count() }} visibles</span></h2><p class="panel-copy">Atención incluye tickets urgentes, vencidos, que vencen hoy o marcados como "requiere atención".</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Proyecto</th><th>Pedido</th><th>Prioridad</th><th>Origen</th><th>Solicitante</th><th>Estado</th><th>Vence</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="mono muted">#{{ $ticket->id }}</td><td>{{ $ticket->project_name }}</td>
                            <td class="title"><a class="task-link" href="{{ route('operational-tickets.show', $ticket) }}">{{ $ticket->title }}</a></td>
                            <td><span class="badge priority-{{ $ticket->priority }}">{{ \App\Models\OperationalTicket::priorityLabel($ticket->priority) }}</span></td>
                            <td>{{ \App\Models\OperationalTicket::sourceLabel($ticket->source) }}</td><td>{{ $ticket->requester ?: 'Sin indicar' }}</td>
                            <td><span class="badge status-{{ $ticket->status }}">{{ \App\Models\OperationalTicket::statusLabel($ticket->status) }}</span></td>
                            <td @class(['due-overdue' => $ticket->due_date?->isBefore(today())])>{{ $ticket->due_date?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No hay tickets que coincidan. Podés cargar el primer pedido manual.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
