@extends('layouts.app')

@section('content')
    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span>Integración local</div>
        <h2>Conectá Gmail para preparar la próxima bandeja de entrada</h2>
        <p>La conexión autoriza lectura de Gmail y datos básicos del perfil. Mientras llega la importación desde la API, podés crear borradores locales desde una cadena completa pegada.</p>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div><p class="section-kicker">Cuenta Google</p><h2>Estado de conexión</h2></div>
            @if ($account?->status === 'connected')
                <span class="badge status-completed">Conectada</span>
            @else
                <span class="badge status-archived">Sin conectar</span>
            @endif
        </div>

        @if (! $configured)
            <div class="errors"><strong>Configuración pendiente.</strong><div>Agregá `GOOGLE_OAUTH_CLIENT_ID` y `GOOGLE_OAUTH_CLIENT_SECRET` al archivo `.env`. Consultá <code>docs/gmail-oauth.md</code> para registrar la URL de redirección local.</div></div>
        @endif

        <dl class="detail">
            <dt>Cuenta</dt><dd>{{ $account?->email_address ?? 'Todavía no hay una cuenta conectada.' }}</dd>
            <dt>Nombre</dt><dd>{{ $account?->display_name ?? 'Sin datos todavía.' }}</dd>
            <dt>Conectada</dt><dd>{{ $account?->connected_at?->format('d/m/Y H:i') ?? 'Sin conexión registrada.' }}</dd>
            <dt>Última sincronización</dt><dd>{{ $account?->last_sync_at?->format('d/m/Y H:i') ?? 'Aún no se importan correos.' }}</dd>
            <dt>Permisos</dt><dd>{{ $account?->scopes ? implode(', ', $account->scopes) : 'Gmail de solo lectura, email y perfil.' }}</dd>
            @if ($account?->error_message)<dt>Error</dt><dd>{{ $account->error_message }}</dd>@endif
        </dl>

        <div class="state-row">
            @if ($account?->status === 'connected')
                <form method="post" action="{{ route('integrations.gmail.disconnect') }}">@csrf <button type="submit" class="reject-button">Desconectar Gmail</button></form>
            @else
                <form method="post" action="{{ route('integrations.gmail.connect') }}">@csrf <button type="submit">Conectar Gmail con Google</button></form>
            @endif
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><p class="section-kicker">Intake local</p><h2>Borrador de ticket desde cadena pegada</h2><p class="panel-copy">Analizá una conversación completa y revisá el ticket propuesto antes de crearlo. No consulta Gmail ni usa un proveedor de IA todavía.</p></div><a class="button-link" href="{{ route('email-thread-imports.create') }}">Crear borrador local</a></div>
    </section>
@endsection
