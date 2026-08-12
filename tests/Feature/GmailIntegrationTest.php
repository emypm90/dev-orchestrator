<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        config()->set('services.google.redirect_uri', '/integrations/gmail/oauth/callback');

        parent::tearDown();
    }

    public function test_gmail_page_renders_missing_configuration_guidance(): void
    {
        $this->get(route('integrations.gmail.index'))
            ->assertOk()
            ->assertSee('Conectá Gmail')
            ->assertSee('Configuración pendiente')
            ->assertSee('GOOGLE_OAUTH_CLIENT_ID', false);
    }

    public function test_connect_is_blocked_with_clear_error_when_google_is_not_configured(): void
    {
        $this->from(route('integrations.gmail.index'))
            ->post(route('integrations.gmail.connect'))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHasErrors('gmail');
    }

    public function test_connect_stores_state_and_redirects_to_google_when_configured(): void
    {
        $this->configureGoogle();

        $this->post(route('integrations.gmail.connect'))
            ->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth?')
            ->assertRedirectContains('client_id=client-id')
            ->assertRedirectContains('access_type=offline')
            ->assertRedirectContains('gmail.readonly')
            ->assertSessionHas('gmail_oauth_state');
    }

    public function test_callback_rejects_missing_or_invalid_state(): void
    {
        $this->get(route('integrations.gmail.callback', ['code' => 'authorization-code']))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHasErrors('gmail');

        $this->withSession(['gmail_oauth_state' => 'expected-state'])
            ->get(route('integrations.gmail.callback', ['code' => 'authorization-code', 'state' => 'wrong-state']))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHasErrors('gmail');
    }

    public function test_callback_stores_a_connected_gmail_account_with_encrypted_tokens(): void
    {
        $this->configureGoogle();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/userinfo.email',
            ]),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'emiliano@18dev.com',
                'name' => 'Emiliano',
            ]),
        ]);

        $this->withSession(['gmail_oauth_state' => 'valid-state'])
            ->get(route('integrations.gmail.callback', ['code' => 'authorization-code', 'state' => 'valid-state']))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHas('success');

        $account = EmailAccount::firstOrFail();
        $this->assertSame('gmail', $account->provider);
        $this->assertSame('connected', $account->status);
        $this->assertSame('emiliano@18dev.com', $account->email_address);
        $this->assertSame('access-token-value', $account->access_token);
        $this->assertSame('refresh-token-value', $account->refresh_token);
        $this->assertNotNull($account->connected_at);
        $this->assertNotSame('access-token-value', DB::table('email_accounts')->value('access_token'));
        $this->assertNotSame('refresh-token-value', DB::table('email_accounts')->value('refresh_token'));

        Http::assertSentCount(2);
    }

    public function test_disconnect_clears_tokens_and_marks_the_account_disconnected(): void
    {
        $account = EmailAccount::create([
            'provider' => 'gmail',
            'email_address' => 'emiliano@18dev.com',
            'access_token' => 'access-token-value',
            'refresh_token' => 'refresh-token-value',
            'status' => 'connected',
            'connected_at' => now(),
        ]);

        $this->post(route('integrations.gmail.disconnect'))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('disconnected', $account->status);
        $this->assertNull($account->access_token);
        $this->assertNull($account->refresh_token);
    }

    private function configureGoogle(): void
    {
        config()->set('services.google.client_id', 'client-id');
        config()->set('services.google.client_secret', 'client-secret');
        config()->set('services.google.redirect_uri', '/integrations/gmail/oauth/callback');
    }
}
