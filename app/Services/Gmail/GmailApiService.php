<?php

namespace App\Services\Gmail;

use App\Models\EmailAccount;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class GmailApiService
{
    public function __construct(private HttpFactory $http, private GoogleOAuthService $google) {}

    public function listThreads(EmailAccount $account, ?string $query = null, int $limit = 10): array
    {
        $response = $this->request($account, 'users/me/threads', [
            'q' => filled($query) ? $query : 'in:inbox newer_than:30d',
            'maxResults' => min(max($limit, 1), 10),
        ]);

        return collect($response['threads'] ?? [])
            ->map(fn (array $thread) => $this->threadMetadata($account, $thread['id']))
            ->all();
    }

    public function fetchThread(EmailAccount $account, string $threadId): array
    {
        $thread = $this->request($account, "users/me/threads/{$threadId}", ['format' => 'full']);
        $messages = $thread['messages'] ?? [];
        $headers = $this->headers($messages[0]['payload']['headers'] ?? []);
        $participants = collect($messages)
            ->flatMap(fn (array $message) => collect(['From', 'To', 'Cc'])
                ->map(fn (string $name) => $this->headers($message['payload']['headers'] ?? [])[$name] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $rawThreadText = collect($messages)->map(function (array $message): string {
            $headers = $this->headers($message['payload']['headers'] ?? []);
            $body = $this->body($message['payload'] ?? []);

            return collect([
                isset($headers['From']) ? "De: {$headers['From']}" : null,
                isset($headers['Date']) ? "Fecha: {$headers['Date']}" : null,
                $body !== '' ? $body : ($message['snippet'] ?? ''),
            ])->filter()->implode("\n");
        })->filter()->implode("\n\n---\n\n");

        return [
            'external_thread_id' => $thread['id'] ?? $threadId,
            'subject' => $headers['Subject'] ?? '(Sin asunto)',
            'participants' => $participants,
            'raw_thread_text' => $rawThreadText,
        ];
    }

    private function threadMetadata(EmailAccount $account, string $threadId): array
    {
        $thread = $this->request($account, "users/me/threads/{$threadId}", [
            'format' => 'metadata',
            'metadataHeaders' => ['Subject', 'From', 'Date'],
        ]);
        $message = ($thread['messages'] ?? [])[0] ?? [];
        $headers = $this->headers($message['payload']['headers'] ?? []);

        return [
            'id' => $thread['id'] ?? $threadId,
            'subject' => $headers['Subject'] ?? '(Sin asunto)',
            'from' => $headers['From'] ?? 'Remitente no disponible',
            'date' => $headers['Date'] ?? null,
            'snippet' => $message['snippet'] ?? $thread['snippet'] ?? '',
        ];
    }

    private function request(EmailAccount $account, string $path, array $query): array
    {
        $token = $this->accessToken($account);
        $response = $this->http->withToken($token)->get("https://gmail.googleapis.com/gmail/v1/{$path}", $query);

        if ($response->failed()) {
            $this->fail($account, 'Gmail no pudo leer los correos. Verificá la conexión e intentá nuevamente.');
        }

        $account->forceFill(['last_sync_at' => now(), 'status' => 'connected', 'error_message' => null])->save();

        return $response->json();
    }

    private function accessToken(EmailAccount $account): string
    {
        if (blank($account->access_token)) {
            $this->fail($account, 'La cuenta Gmail no tiene un token de acceso. Volvé a conectarla.');
        }

        if ($account->token_expires_at?->lte(now()->addMinute())) {
            if (blank($account->refresh_token)) {
                $this->fail($account, 'El acceso a Gmail venció y no hay token de renovación. Volvé a conectarla.');
            }

            try {
                $tokens = $this->google->refreshAccessToken($account->refresh_token);
            } catch (RuntimeException) {
                $this->fail($account, 'No se pudo renovar el acceso a Gmail. Volvé a conectar la cuenta.');
            }

            $account->forceFill([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
                'token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
                'status' => 'connected',
                'error_message' => null,
            ])->save();
        }

        return $account->access_token;
    }

    private function fail(EmailAccount $account, string $message): never
    {
        $account->forceFill(['status' => 'error', 'error_message' => $message])->save();

        throw new RuntimeException($message);
    }

    private function headers(array $headers): array
    {
        return collect($headers)->mapWithKeys(fn (array $header) => [$header['name'] => $header['value']])->all();
    }

    private function body(array $payload): string
    {
        if (isset($payload['body']['data'])) {
            return trim(base64_decode(strtr($payload['body']['data'], '-_', '+/')) ?: '');
        }

        return collect($payload['parts'] ?? [])
            ->map(fn (array $part) => $this->body($part))
            ->filter()
            ->implode("\n");
    }
}
