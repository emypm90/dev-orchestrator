<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\EmailThreadImport;
use App\Services\Gmail\GmailApiService;
use App\Services\Gmail\GoogleOAuthService;
use App\Services\Gmail\ThreadTicketDraftGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GmailIntegrationController extends Controller
{
    public function index(GoogleOAuthService $google, ThreadTicketDraftGenerator $generator)
    {
        return view('integrations.gmail', [
            'account' => EmailAccount::query()->where('provider', 'gmail')->latest('connected_at')->first(),
            'configured' => $google->isConfigured(),
            'draftProvider' => $generator->providerLabel(),
        ]);
    }

    public function threads(Request $request, GmailApiService $gmail, ThreadTicketDraftGenerator $generator): mixed
    {
        $account = EmailAccount::query()->where('provider', 'gmail')->where('status', 'connected')->latest('connected_at')->first();

        if (! $account) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => 'Conectá una cuenta Gmail antes de buscar correos.']);
        }

        try {
            $threads = $gmail->listThreads($account, $request->string('query')->trim()->toString());
        } catch (\RuntimeException $exception) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => $exception->getMessage()]);
        }

        return view('integrations.gmail', [
            'account' => $account->fresh(),
            'configured' => app(GoogleOAuthService::class)->isConfigured(),
            'threads' => $threads,
            'query' => $request->string('query')->trim()->toString(),
            'draftProvider' => $generator->providerLabel(),
        ]);
    }

    public function importThread(string $threadId, GmailApiService $gmail, ThreadTicketDraftGenerator $generator): RedirectResponse
    {
        $account = EmailAccount::query()->where('provider', 'gmail')->where('status', 'connected')->latest('connected_at')->first();

        if (! $account) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => 'Conectá una cuenta Gmail antes de importar una cadena.']);
        }

        try {
            $thread = $gmail->fetchThread($account, $threadId);
        } catch (\RuntimeException $exception) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => $exception->getMessage()]);
        }

        $import = EmailThreadImport::query()->firstOrNew([
            'provider' => 'gmail',
            'external_thread_id' => $thread['external_thread_id'],
        ]);

        if (! $import->exists || $import->status === 'draft') {
            $draft = $generator->generate($thread['subject'], $thread['participants'], $thread['raw_thread_text']);
            $import->fill([
                'email_account_id' => $account->id,
                'subject' => $thread['subject'],
                'participants' => $thread['participants'],
                'raw_thread_text' => $thread['raw_thread_text'],
                'draft_generator' => $generator->providerLabel(),
                'ai_summary' => $draft['summary'],
                'ai_expectations' => $draft['functional_expectations'],
                'ai_questions' => $draft['open_questions'],
                'proposed_ticket_payload' => $draft,
                'status' => 'draft',
                'error_message' => null,
            ])->save();
        }

        return redirect()->route('email-thread-imports.show', $import);
    }

    public function connect(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        if (! $google->isConfigured()) {
            return back()->withErrors(['gmail' => 'Completá la configuración avanzada de Google OAuth antes de conectar Gmail.']);
        }

        $state = Str::random(64);
        $request->session()->put('gmail_oauth_state', $state);

        return redirect()->away($google->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        $state = $request->session()->pull('gmail_oauth_state');

        if (! is_string($state) || ! is_string($request->state) || ! hash_equals($state, $request->state)) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => 'La conexión con Google expiró o no es válida. Intentá nuevamente.']);
        }

        if ($request->filled('error')) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => 'Google canceló o rechazó la autorización.']);
        }

        if (! $request->filled('code')) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => 'Google no devolvió un código de autorización.']);
        }

        try {
            $tokens = $google->exchangeCode($request->string('code')->toString());
            $profile = $google->userInfo($tokens['access_token']);
            $account = EmailAccount::query()->firstOrNew([
                'provider' => 'gmail',
                'email_address' => $profile['email'],
            ]);

            $account->fill([
                'display_name' => $profile['name'] ?? null,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
                'token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
                'scopes' => isset($tokens['scope']) ? preg_split('/\s+/', trim($tokens['scope'])) : config('services.google.scopes'),
                'connected_at' => now(),
                'status' => 'connected',
                'error_message' => null,
            ])->save();
        } catch (Throwable) {
            return redirect()->route('integrations.gmail.index')->withErrors(['gmail' => 'No se pudo completar la conexión con Google. Revisá la configuración e intentá nuevamente.']);
        }

        return redirect()->route('integrations.gmail.index')->with('success', 'Gmail quedó conectado localmente. Todavía no se importaron correos.');
    }

    public function disconnect(): RedirectResponse
    {
        EmailAccount::query()->where('provider', 'gmail')->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => 'disconnected',
            'error_message' => null,
        ]);

        return redirect()->route('integrations.gmail.index')->with('success', 'Gmail quedó desconectado y los tokens locales fueron eliminados.');
    }
}
