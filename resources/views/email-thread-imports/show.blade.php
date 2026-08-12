@extends('layouts.app', ['title' => 'Revisar borrador Gmail', 'heading' => 'Revisión de borrador Gmail'])

@section('content')
    <a class="back-link" href="{{ route('email-thread-imports.create') }}">&larr; Crear otro borrador</a>
    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span>Revisión requerida</div>
        <h2>{{ $import->subject }}</h2>
        <p>Generado con: {{ $import->draft_generator ?? 'Generador local determinista' }}. Este análisis es un borrador generado desde una cadena de correo. Revisalo antes de crear un ticket operativo.</p>
    </section>
    <section class="panel">
        <div class="decision-summary">
            <section class="summary-card summary-objective"><span class="section-kicker">Resumen AI</span><p>{{ $import->ai_summary }}</p></section>
            <section class="summary-card"><span class="section-kicker">Ticket propuesto</span><h3>{{ $import->proposed_ticket_payload['title'] }}</h3><p>Proyecto: {{ $import->proposed_ticket_payload['project_name'] }}<br>Solicitante: {{ $import->proposed_ticket_payload['requester'] ?: 'Sin indicar' }}<br>Prioridad: {{ \App\Models\OperationalTicket::priorityLabel($import->proposed_ticket_payload['priority']) }}</p></section>
            <section class="summary-card"><span class="section-kicker">Objetivo</span><p>{{ $import->proposed_ticket_payload['objective'] }}</p></section>
            <section class="summary-card"><span class="section-kicker">Participantes</span><p>{{ $import->participants ? implode(', ', $import->participants) : 'Sin indicar' }}</p></section>
        </div>
    </section>
    <section class="panel">
        <div class="expectations">
            <section><h3>Expectativas funcionales</h3><ul>@foreach ($import->ai_expectations ?? [] as $expectation)<li>{{ $expectation }}</li>@endforeach</ul></section>
            <section><h3>Preguntas abiertas</h3><ul>@foreach ($import->ai_questions ?? [] as $question)<li>{{ $question }}</li>@endforeach</ul></section>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><h2>Crear ticket operativo</h2><p class="panel-copy">Al confirmar, el ticket se crea en triage. Las expectativas y preguntas quedan incluidas en el objetivo para conservarlas en el flujo operativo existente.</p></div></div>
        @if ($import->operationalTicket)
            <p>Este borrador ya creó <a href="{{ route('operational-tickets.show', $import->operationalTicket) }}>el ticket operativo #{{ $import->operationalTicket->id }}</a>.</p>
        @else
            <form method="POST" action="{{ route('email-thread-imports.create-ticket', $import) }}">@csrf <button type="submit">Crear ticket operativo en triage</button></form>
        @endif
    </section>
@endsection
