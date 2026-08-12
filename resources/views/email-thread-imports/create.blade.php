@extends('layouts.app', ['title' => 'Borrador desde Gmail', 'heading' => 'Intake de Gmail'])

@section('content')
    <a class="back-link" href="{{ route('integrations.gmail.index') }}">&larr; Volver a integración Gmail</a>
    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span>{{ $draftProvider }}</div>
        <h2>Creá un borrador desde una cadena pegada</h2>
        <p>Pegá la conversación completa. Generación actual: {{ $draftProvider }}. El borrador requiere revisión antes de crear el ticket.</p>
    </section>
    <section class="panel">
        <form class="ticket-form" method="POST" action="{{ route('email-thread-imports.store') }}">
            @csrf
            <label>Asunto<input name="subject" value="{{ old('subject') }}" required autofocus></label>
            <label>Participantes <span class="muted">(opcional, separados por coma)</span><input name="participants" value="{{ old('participants') }}"></label>
            <label class="wide-field">Cadena completa<textarea name="raw_thread_text" required>{{ old('raw_thread_text') }}</textarea></label>
            <div class="wide-field"><button type="submit">Generar borrador para revisión</button></div>
        </form>
    </section>
@endsection
