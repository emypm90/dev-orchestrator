<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailAccount extends Model
{
    public const PROVIDERS = ['gmail'];

    public const STATUSES = ['disconnected', 'connected', 'error'];

    protected $fillable = [
        'provider', 'email_address', 'display_name', 'access_token', 'refresh_token',
        'token_expires_at', 'scopes', 'connected_at', 'last_sync_at', 'status', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }
}
