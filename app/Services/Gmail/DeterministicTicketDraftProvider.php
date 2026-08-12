<?php

namespace App\Services\Gmail;

class DeterministicTicketDraftProvider
{
    public function generate(string $subject, array $participants, string $threadText): array
    {
        $lines = collect(preg_split('/\R+/', trim($threadText)))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();
        $expectations = $lines
            ->filter(fn (string $line) => preg_match('/\b(debe|deben|espera|esperan|requiere|requerimos|necesita|necesitamos|should|must|needs to)\b/ui', $line) === 1)
            ->take(5)
            ->values()
            ->all();
        $questions = $lines
            ->filter(fn (string $line) => str_contains($line, '?'))
            ->take(5)
            ->values()
            ->all();

        if ($expectations === []) {
            $expectations[] = 'Confirmar el comportamiento esperado con la persona solicitante antes de implementar.';
        }

        if ($questions === []) {
            $questions[] = '¿Hay criterios de aceptación, fecha límite o alcance adicional que deban confirmarse?';
        }

        $summary = $lines->take(3)->implode(' ');

        return [
            'project_name' => 'Sin clasificar',
            'requester' => $participants[0] ?? null,
            'title' => $subject,
            'original_text' => $threadText,
            'context' => $threadText,
            'objective' => 'Resolver el pedido descrito en la cadena de correo.',
            'priority' => 'normal',
            'summary' => $summary !== '' ? $summary : $subject,
            'functional_expectations' => $expectations,
            'open_questions' => $questions,
        ];
    }
}
