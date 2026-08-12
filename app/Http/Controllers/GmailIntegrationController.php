<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Services\Gmail\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GmailIntegrationController extends Controller
{
    public function index(GoogleOAuthService $google)
    {
        return view('integrations.gmail', [
            'account' => EmailAccount::query()->where('provider', 'gmail')->latest('connected_at')->first(),
            'configured' => $google->isConfigured(),
        ]);
    }

    public function connect(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        if (! $google->isConfigured()) {
            return back()->withErrors(['gmail' => 'Configurá GOOGLE_OAUTH_CLIENT_ID y GOOGLE_OAUTH_CLIENT_SECRET antes de conectar Gmail.']);
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
