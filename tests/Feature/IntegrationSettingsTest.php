<?php

namespace Tests\Feature;

use App\Models\IntegrationSettings;
use App\Services\Gmail\GoogleOAuthService;
use App\Services\Gmail\ThreadTicketDraftGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect_uri', '/integrations/gmail/oauth/callback');
        config()->set('services.openai.ticket_draft.key', null);
        config()->set('services.openai.ticket_draft.model', 'gpt-5.5');
        config()->set('services.openai.ticket_draft.enabled', true);

        parent::tearDown();
    }

    public function test_settings_page_renders_effective_fallback_values_and_masks_secrets(): void
    {
        config()->set('services.google.client_id', 'env-google-client');
        config()->set('services.google.client_secret', 'env-google-secret');
        config()->set('services.openai.ticket_draft.key', 'env-openai-secret');

        $this->get(route('settings.integrations.edit'))
            ->assertOk()
            ->assertSee('Configuración avanzada de Google OAuth')
            ->assertSee('env-google-client')
            ->assertSee('*******cret')
            ->assertSee('*************cret')
            ->assertDontSee('env-google-secret')
            ->assertDontSee('env-openai-secret');
    }

    public function test_saving_settings_encrypts_secrets_and_persists_non_secret_values(): void
    {
        $this->put(route('settings.integrations.update'), [
            'google_oauth_client_id' => 'dashboard-google-client',
            'google_oauth_client_secret' => 'dashboard-google-secret',
            'google_oauth_redirect_uri' => 'http://localhost/google/callback',
            'openai_api_key' => 'dashboard-openai-secret',
            'openai_ticket_draft_model' => 'gpt-5.5-mini',
            'openai_ticket_draft_enabled' => true,
        ])->assertRedirect(route('settings.integrations.edit'));

        $settings = IntegrationSettings::firstOrFail();
        $this->assertSame('dashboard-google-client', $settings->google_oauth_client_id);
        $this->assertSame('dashboard-google-secret', $settings->google_oauth_client_secret);
        $this->assertSame('dashboard-openai-secret', $settings->openai_api_key);
        $this->assertSame('gpt-5.5-mini', $settings->openai_ticket_draft_model);
        $this->assertTrue($settings->openai_ticket_draft_enabled);
        $this->assertNotSame('dashboard-google-secret', DB::table('integration_settings')->value('google_oauth_client_secret'));
        $this->assertNotSame('dashboard-openai-secret', DB::table('integration_settings')->value('openai_api_key'));
    }

    public function test_blank_secret_inputs_preserve_existing_encrypted_values(): void
    {
        $settings = IntegrationSettings::create([
            'google_oauth_client_secret' => 'existing-google-secret',
            'openai_api_key' => 'existing-openai-secret',
        ]);

        $this->put(route('settings.integrations.update'), [
            'google_oauth_client_id' => 'updated-google-client',
            'google_oauth_client_secret' => '',
            'openai_api_key' => '',
            'openai_ticket_draft_enabled' => false,
        ])->assertRedirect(route('settings.integrations.edit'));

        $settings->refresh();
        $this->assertSame('existing-google-secret', $settings->google_oauth_client_secret);
        $this->assertSame('existing-openai-secret', $settings->openai_api_key);
    }

    public function test_google_oauth_uses_dashboard_credentials_before_environment_values(): void
    {
        config()->set('services.google.client_id', 'env-client');
        config()->set('services.google.client_secret', 'env-secret');
        IntegrationSettings::create([
            'google_oauth_client_id' => 'dashboard-client',
            'google_oauth_client_secret' => 'dashboard-secret',
            'google_oauth_redirect_uri' => 'http://localhost/dashboard-callback',
        ]);

        $url = app(GoogleOAuthService::class)->authorizationUrl('state');

        $this->assertStringContainsString('client_id=dashboard-client', $url);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%2Fdashboard-callback', $url);
        $this->assertStringNotContainsString('env-client', $url);
    }

    public function test_openai_uses_dashboard_settings_before_environment_values(): void
    {
        config()->set('services.openai.ticket_draft.key', 'env-openai-key');
        config()->set('services.openai.ticket_draft.model', 'env-model');
        IntegrationSettings::create([
            'openai_api_key' => 'dashboard-openai-key',
            'openai_ticket_draft_model' => 'dashboard-model',
            'openai_ticket_draft_enabled' => true,
        ]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([], 500)]);

        $generator = app(ThreadTicketDraftGenerator::class);
        $generator->generate('Asunto', ['Lucía'], 'Texto de prueba');

        $this->assertSame('OpenAI (dashboard-model)', $generator->providerLabel());
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === 'dashboard-model'
            && $request->hasHeader('Authorization', 'Bearer dashboard-openai-key'));
    }

    public function test_disabling_openai_in_dashboard_forces_local_fallback_despite_environment_key(): void
    {
        config()->set('services.openai.ticket_draft.key', 'env-openai-key');
        config()->set('services.openai.ticket_draft.enabled', true);
        IntegrationSettings::create(['openai_ticket_draft_enabled' => false]);
        Http::fake();

        $generator = app(ThreadTicketDraftGenerator::class);

        $this->assertFalse($generator->usesOpenAi());
        $this->assertSame('Generador local determinista', $generator->providerLabel());
        $generator->generate('Asunto', ['Lucía'], 'Texto de prueba');
        Http::assertNothingSent();
    }
}
