<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\EmailThreadImport;
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

    public function test_gmail_page_renders_a_simple_connection_flow_when_disconnected(): void
    {
        $this->get(route('integrations.gmail.index'))
            ->assertOk()
            ->assertSee('Conectá tu cuenta Gmail')
            ->assertSee('Conectar con Google')
            ->assertSee('configuración avanzada de Google OAuth')
            ->assertDontSee('GOOGLE_OAUTH_CLIENT_ID', false);
    }

    public function test_gmail_page_shows_the_connected_account_and_reconnection_actions(): void
    {
        $this->connectedAccount(['display_name' => 'Emiliano']);

        $this->get(route('integrations.gmail.index'))
            ->assertOk()
            ->assertSee('Gmail está conectado')
            ->assertSee('emiliano@18dev.com')
            ->assertSee('Reconectar con Google')
            ->assertSee('Desconectar Gmail');
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

    public function test_short_callback_route_rejects_missing_state_with_the_existing_callback_logic(): void
    {
        $this->get('/integrations/gmail/callback?code=authorization-code')
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

    public function test_thread_listing_requires_a_connected_account(): void
    {
        $this->get(route('integrations.gmail.threads'))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHasErrors('gmail');
    }

    public function test_thread_listing_reads_metadata_from_gmail_and_renders_it(): void
    {
        $this->connectedAccount();
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/threads/thread-123*' => Http::response([
                'id' => 'thread-123',
                'messages' => [[
                    'snippet' => 'Necesitamos ajustar la pantalla.',
                    'payload' => ['headers' => [
                        ['name' => 'Subject', 'value' => 'Ajustar pantalla'],
                        ['name' => 'From', 'value' => 'Lucia <lucia@example.com>'],
                        ['name' => 'Date', 'value' => 'Tue, 12 Aug 2026 10:00:00 +0000'],
                    ]],
                ]],
            ]),
            'https://gmail.googleapis.com/gmail/v1/users/me/threads*' => Http::response([
                'threads' => [['id' => 'thread-123']],
            ]),
        ]);

        $this->get(route('integrations.gmail.threads', ['query' => 'in:inbox']))
            ->assertOk()
            ->assertSee('Ajustar pantalla')
            ->assertSee('Lucia &lt;lucia@example.com&gt;', false)
            ->assertSee('Necesitamos ajustar la pantalla.');

        Http::assertSentCount(2);
    }

    public function test_importing_a_gmail_thread_creates_a_review_draft(): void
    {
        $this->connectedAccount();
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/threads/thread-123*' => Http::response($this->fullThread()),
        ]);

        $response = $this->post(route('integrations.gmail.threads.import', 'thread-123'));

        $import = EmailThreadImport::firstOrFail();
        $response->assertRedirect(route('email-thread-imports.show', $import));
        $this->assertSame('thread-123', $import->external_thread_id);
        $this->assertSame('Corregir formulario de contacto', $import->subject);
        $this->assertSame(['Lucia <lucia@example.com>', 'Equipo web <web@example.com>'], $import->participants);
        $this->assertStringContainsString('El formulario debe mostrar el campo Empresa.', $import->raw_thread_text);
        $this->assertSame('Corregir formulario de contacto', $import->proposed_ticket_payload['title']);
    }

    public function test_importing_the_same_gmail_thread_reuses_its_draft(): void
    {
        $this->connectedAccount();
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/threads/thread-123*' => Http::response($this->fullThread()),
        ]);

        $this->post(route('integrations.gmail.threads.import', 'thread-123'));
        $this->post(route('integrations.gmail.threads.import', 'thread-123'));

        $this->assertDatabaseCount('email_thread_imports', 1);
    }

    public function test_expired_token_is_refreshed_before_reading_gmail(): void
    {
        $account = $this->connectedAccount(['token_expires_at' => now()->subMinute()]);
        $this->configureGoogle();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'renewed-access-token', 'expires_in' => 3600]),
            'https://gmail.googleapis.com/gmail/v1/users/me/threads*' => Http::response(['threads' => []]),
        ]);

        $this->get(route('integrations.gmail.threads'))->assertOk();

        $account->refresh();
        $this->assertSame('renewed-access-token', $account->access_token);
        $this->assertTrue($account->token_expires_at->isFuture());
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token');
    }

    public function test_gmail_api_failure_is_shown_and_recorded_on_the_account(): void
    {
        $account = $this->connectedAccount();
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/threads*' => Http::response([], 500),
        ]);

        $this->get(route('integrations.gmail.threads'))
            ->assertRedirect(route('integrations.gmail.index'))
            ->assertSessionHasErrors('gmail');

        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertSame('Gmail no pudo leer los correos. Verificá la conexión e intentá nuevamente.', $account->error_message);
    }

    private function configureGoogle(): void
    {
        config()->set('services.google.client_id', 'client-id');
        config()->set('services.google.client_secret', 'client-secret');
        config()->set('services.google.redirect_uri', '/integrations/gmail/oauth/callback');
    }

    private function connectedAccount(array $attributes = []): EmailAccount
    {
        return EmailAccount::create(array_merge([
            'provider' => 'gmail',
            'email_address' => 'emiliano@18dev.com',
            'access_token' => 'access-token-value',
            'refresh_token' => 'refresh-token-value',
            'token_expires_at' => now()->addHour(),
            'status' => 'connected',
            'connected_at' => now(),
        ], $attributes));
    }

    private function fullThread(): array
    {
        return [
            'id' => 'thread-123',
            'messages' => [[
                'snippet' => 'Necesitamos ajustar el formulario.',
                'payload' => [
                    'headers' => [
                        ['name' => 'Subject', 'value' => 'Corregir formulario de contacto'],
                        ['name' => 'From', 'value' => 'Lucia <lucia@example.com>'],
                        ['name' => 'To', 'value' => 'Equipo web <web@example.com>'],
                        ['name' => 'Date', 'value' => 'Tue, 12 Aug 2026 10:00:00 +0000'],
                    ],
                    'body' => ['data' => rtrim(strtr(base64_encode('El formulario debe mostrar el campo Empresa.'), '+/', '-_'), '=')],
                ],
            ]],
        ];
    }
}
