<?php

namespace App\Services\Gmail;

use App\Services\Integrations\IntegrationSettingsResolver;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

class OpenAiTicketDraftProvider
{
    public function __construct(private HttpFactory $http, private IntegrationSettingsResolver $settings) {}

    public function generate(string $prompt): ?array
    {
        $settings = $this->settings->openAiTicketDraft();
        $apiKey = $settings['key'];

        if (! is_string($apiKey) || $apiKey === '') {
            return null;
        }

        try {
            $response = $this->http->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $settings['model'],
                    'input' => $prompt,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'operational_ticket_draft',
                            'strict' => true,
                            'schema' => $this->schema(),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                return null;
            }

            $text = data_get($response->json(), 'output.0.content.0.text');

            if (! is_string($text)) {
                return null;
            }

            $payload = json_decode($text, true);

            return is_array($payload) ? $payload : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function label(): string
    {
        return 'OpenAI ('.$this->settings->openAiTicketDraft()['model'].')';
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_name' => ['type' => 'string'],
                'requester' => ['type' => ['string', 'null']],
                'title' => ['type' => 'string'],
                'original_text' => ['type' => 'string'],
                'context' => ['type' => 'string'],
                'objective' => ['type' => 'string'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                'summary' => ['type' => 'string'],
                'functional_expectations' => ['type' => 'array', 'items' => ['type' => 'string']],
                'open_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['project_name', 'requester', 'title', 'original_text', 'context', 'objective', 'priority', 'summary', 'functional_expectations', 'open_questions'],
            'additionalProperties' => false,
        ];
    }
}
