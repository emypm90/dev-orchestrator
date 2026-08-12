@extends('layouts.app', ['title' => 'Configuración de integraciones', 'heading' => 'Configuración de integraciones'])

@section('content')
    <section class="panel command-hero">
        <div class="hero-signal"><span class="signal-dot"></span>Configuración local</div>
        <h2>Credenciales para las integraciones</h2>
        <p>Los secretos se guardan en SQLite cifrados con `APP_KEY`. Si no configurás un valor acá, la aplicación mantiene el fallback opcional del archivo `.env`.</p>
    </section>
    <form method="post" action="{{ route('settings.integrations.update') }}">
        @csrf
        @method('put')
        <section class="panel">
            <div class="panel-header"><div><p class="section-kicker">Google OAuth</p><h2>Conexión de Gmail</h2><p class="panel-copy">Usá la URL de redirección autorizada en Google Cloud.</p></div></div>
            <div class="form-grid">
                <label>ID de cliente<input name="google_oauth_client_id" value="{{ old('google_oauth_client_id', $settings?->google_oauth_client_id ?? $google['client_id']) }}" autocomplete="off"></label>
                <label>Secreto de cliente <span class="muted">({{ \App\Models\IntegrationSettings::mask($google['client_secret']) ?? 'sin configurar' }})</span><input type="password" name="google_oauth_client_secret" autocomplete="new-password" placeholder="Dejá vacío para conservarlo"></label>
                <label class="wide-field">URI de redirección<input name="google_oauth_redirect_uri" value="{{ old('google_oauth_redirect_uri', $settings?->google_oauth_redirect_uri ?? $google['redirect_uri']) }}" placeholder="/integrations/gmail/oauth/callback"></label>
            </div>
        </section>
        <section class="panel">
            <div class="panel-header"><div><p class="section-kicker">OpenAI</p><h2>Borradores de tickets</h2><p class="panel-copy">Al habilitarlo, el contenido de las cadenas se envía a OpenAI para proponer un borrador revisable.</p></div></div>
            <div class="form-grid">
                <label>Clave de API <span class="muted">({{ \App\Models\IntegrationSettings::mask($openAi['key']) ?? 'sin configurar' }})</span><input type="password" name="openai_api_key" autocomplete="new-password" placeholder="Dejá vacío para conservarla"></label>
                <label>Modelo<input name="openai_ticket_draft_model" value="{{ old('openai_ticket_draft_model', $settings?->openai_ticket_draft_model ?? $openAi['model'] ?? 'gpt-5.5') }}"></label>
                <label><input type="hidden" name="openai_ticket_draft_enabled" value="0"><input type="checkbox" name="openai_ticket_draft_enabled" value="1" @checked(old('openai_ticket_draft_enabled', $openAi['enabled']))> Habilitar borradores con OpenAI</label>
                <div class="wide-field"><button type="submit">Guardar configuración local</button></div>
            </div>
        </section>
    </form>
@endsection
