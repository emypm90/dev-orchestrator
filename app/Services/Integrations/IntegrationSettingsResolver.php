<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSettings;

class IntegrationSettingsResolver
{
    public function google(): array
    {
        $settings = IntegrationSettings::query()->first();

        return [
            'client_id' => filled($settings?->google_oauth_client_id) ? $settings->google_oauth_client_id : config('services.google.client_id'),
            'client_secret' => filled($settings?->google_oauth_client_secret) ? $settings->google_oauth_client_secret : config('services.google.client_secret'),
            'redirect_uri' => filled($settings?->google_oauth_redirect_uri) ? $settings->google_oauth_redirect_uri : config('services.google.redirect_uri'),
            'scopes' => config('services.google.scopes'),
        ];
    }

    public function openAiTicketDraft(): array
    {
        $settings = IntegrationSettings::query()->first();

        return [
            'key' => filled($settings?->openai_api_key) ? $settings->openai_api_key : config('services.openai.ticket_draft.key'),
            'model' => filled($settings?->openai_ticket_draft_model) ? $settings->openai_ticket_draft_model : config('services.openai.ticket_draft.model'),
            'enabled' => $settings?->openai_ticket_draft_enabled ?? filter_var(config('services.openai.ticket_draft.enabled'), FILTER_VALIDATE_BOOL),
        ];
    }
}
