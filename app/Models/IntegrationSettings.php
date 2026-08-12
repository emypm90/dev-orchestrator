<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSettings extends Model
{
    protected $fillable = [
        'google_oauth_client_id', 'google_oauth_client_secret', 'google_oauth_redirect_uri',
        'openai_api_key', 'openai_ticket_draft_model', 'openai_ticket_draft_enabled',
    ];

    protected function casts(): array
    {
        return [
            'google_oauth_client_secret' => 'encrypted',
            'openai_api_key' => 'encrypted',
            'openai_ticket_draft_enabled' => 'boolean',
        ];
    }

    public static function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_repeat('*', max(4, strlen($value) - 4)).substr($value, -4);
    }
}
