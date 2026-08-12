<?php

namespace App\Services\Gmail;

use App\Services\Integrations\IntegrationSettingsResolver;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class GoogleOAuthService
{
    public function __construct(private HttpFactory $http, private IntegrationSettingsResolver $settings) {}

    public function isConfigured(): bool
    {
        $google = $this->settings->google();

        return filled($google['client_id']) && filled($google['client_secret']);
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->settings->google()['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->settings->google()['scopes']),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $response = $this->http->asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->settings->google()['client_id'],
            'client_secret' => $this->settings->google()['client_secret'],
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('Google no pudo intercambiar el código de autorización.');
        }

        return $response->json();
    }

    public function userInfo(string $accessToken): array
    {
        $response = $this->http->withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if ($response->failed() || blank($response->json('email'))) {
            throw new RuntimeException('Google no devolvió una dirección de correo para esta cuenta.');
        }

        return $response->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->http->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->settings->google()['client_id'],
            'client_secret' => $this->settings->google()['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('Google no pudo renovar el acceso a Gmail.');
        }

        return $response->json();
    }

    private function redirectUri(): string
    {
        $uri = $this->settings->google()['redirect_uri'];

        return str_starts_with($uri, 'http') ? $uri : url($uri);
    }
}
