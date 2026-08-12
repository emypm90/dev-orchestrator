<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSettings;
use App\Services\Integrations\IntegrationSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationSettingsController extends Controller
{
    public function edit(IntegrationSettingsResolver $resolver)
    {
        $settings = IntegrationSettings::query()->first();

        return view('settings.integrations', [
            'settings' => $settings,
            'google' => $resolver->google(),
            'openAi' => $resolver->openAiTicketDraft(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'google_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'google_oauth_client_secret' => ['nullable', 'string', 'max:2048'],
            'google_oauth_redirect_uri' => ['nullable', 'string', 'max:2048'],
            'openai_api_key' => ['nullable', 'string', 'max:2048'],
            'openai_ticket_draft_model' => ['nullable', 'string', 'max:255'],
            'openai_ticket_draft_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = IntegrationSettings::query()->firstOrNew();
        $settings->fill([
            'google_oauth_client_id' => $values['google_oauth_client_id'] ?? null,
            'google_oauth_redirect_uri' => $values['google_oauth_redirect_uri'] ?? null,
            'openai_ticket_draft_model' => $values['openai_ticket_draft_model'] ?? null,
            'openai_ticket_draft_enabled' => $request->boolean('openai_ticket_draft_enabled'),
        ]);

        foreach (['google_oauth_client_secret', 'openai_api_key'] as $secret) {
            if (filled($values[$secret] ?? null)) {
                $settings->{$secret} = $values[$secret];
            }
        }

        $settings->save();

        return redirect()->route('settings.integrations.edit')->with('success', 'La configuración local de integraciones fue guardada.');
    }
}
