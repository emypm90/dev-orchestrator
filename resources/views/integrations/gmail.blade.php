@extends('layouts.app')

@section('content')
    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span>Integración local</div>
        <h2>Conectá Gmail para preparar la próxima bandeja de entrada</h2>
        <p>La conexión autoriza lectura de Gmail y datos básicos del perfil. Buscá cadenas reales, creá un borrador y revisalo antes de crear un ticket.</p>
    </section>
    <section class="panel">
        <div class="panel-header"><div><p class="section-kicker">Gmail de solo lectura</p><h2>Buscar cadenas recientes</h2><p class="panel-copy">La importación nunca crea un ticket automáticamente: primero abre el borrador para revisión.</p></div></div>
        @if ($account?->status === 'connected')
            <form method="get" action="{{ route('integrations.gmail.threads') }}" class="form-grid">
                <label>Consulta Gmail <input name="query" value="{{ $query ?? 'in:inbox newer_than:30d' }}" placeholder="in:inbox newer_than:30d"></label>
                <div><button type="submit">Buscar cadenas</button></div>
            </form>
            @isset($threads)
                <div class="table-wrap"><table><thead><tr><th>Asunto</th><th>De</th><th>Fecha</th><th>Vista previa</th><th></th></tr></thead><tbody>
                @forelse ($threads as $thread)
                    <tr><td>{{ $thread['subject'] }}</td><td>{{ $thread['from'] }}</td><td>{{ $thread['date'] }}</td><td>{{ $thread['snippet'] }}</td><td><form method="post" action="{{ route('integrations.gmail.threads.import', $thread['id']) }}">@csrf <button type="submit">Crear borrador</button></form></td></tr>
                @empty
                    <tr><td colspan="5">No se encontraron cadenas para esta consulta.</td></tr>
                @endforelse
                </tbody></table></div>
            @endisset
        @else
            <p class="panel-copy">Conectá Gmail para buscar e importar cadenas reales de solo lectura.</p>
        @endif
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
            <div class="errors"><strong>Configuración pendiente.</strong><div>Completá las credenciales en <a href="{{ route('settings.integrations.edit') }}">Configuración</a> o agregá `GOOGLE_OAUTH_CLIENT_ID` y `GOOGLE_OAUTH_CLIENT_SECRET` al archivo `.env`. Consultá <code>docs/gmail-oauth.md</code> para registrar la URL de redirección local.</div></div>
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
        <div class="panel-header"><div><p class="section-kicker">Intake de correo</p><h2>Borrador de ticket desde cadena pegada</h2><p class="panel-copy">Generación actual: {{ $draftProvider }}. Analizá una conversación completa y revisá el ticket propuesto antes de crearlo.</p></div><a class="button-link" href="{{ route('email-thread-imports.create') }}">Crear borrador</a></div>
    </section>
@endsection
