<?php

namespace App\Services\Gmail;

class ThreadTicketDraftGenerator
{
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private DeterministicTicketDraftProvider $deterministic,
        private OpenAiTicketDraftProvider $openAi,
    ) {}

    public function generate(string $subject, array $participants, string $threadText): array
    {
        $fallback = $this->deterministic->generate($subject, $participants, $threadText);

        if (! $this->usesOpenAi()) {
            return $fallback;
        }

        return $this->normalize($this->openAi->generate($this->prompt($subject, $participants, $threadText)), $threadText) ?? $fallback;
    }

    public function providerLabel(): string
    {
        return $this->usesOpenAi() ? $this->openAi->label() : 'Generador local determinista';
    }

    public function usesOpenAi(): bool
    {
        return filter_var(config('services.openai.ticket_draft.enabled'), FILTER_VALIDATE_BOOL)
            && filled(config('services.openai.ticket_draft.key'));
    }

    /**
     * The provider receives the full thread but only this structured draft is retained.
     */
    public function prompt(string $subject, array $participants, string $threadText): string
    {
        return "Analizá la cadena completa de Gmail y devolvé únicamente el JSON solicitado. Es un borrador operativo para revisión humana obligatoria: no crees tickets ni inventes hechos. Conservá los requisitos explícitos y convertí ambigüedades en open_questions.\n\n"
            ."Campos requeridos: project_name, requester, title, original_text, context, objective, priority (low|normal|high|urgent), summary, functional_expectations (array de strings) y open_questions (array de strings).\n\n"
            ."Asunto: {$subject}\nParticipantes: ".implode(', ', $participants)."\n\nCadena completa:\n{$threadText}";
    }

    private function normalize(?array $payload, string $threadText): ?array
    {
        if ($payload === null) {
            return null;
        }

        foreach (['project_name', 'title', 'context', 'objective', 'summary'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
                return null;
            }
        }

        if (isset($payload['requester']) && ! is_string($payload['requester'])) {
            return null;
        }

        if (! isset($payload['priority']) || ! is_string($payload['priority']) || ! in_array($payload['priority'], self::PRIORITIES, true)) {
            return null;
        }

        foreach (['functional_expectations', 'open_questions'] as $field) {
            if (! isset($payload[$field]) || ! is_array($payload[$field]) || collect($payload[$field])->contains(fn ($item) => ! is_string($item) || trim($item) === '')) {
                return null;
            }
        }

        // Preserve the imported evidence rather than trusting a model echo of it.
        $payload['original_text'] = $threadText;

        return $payload;
    }
}
