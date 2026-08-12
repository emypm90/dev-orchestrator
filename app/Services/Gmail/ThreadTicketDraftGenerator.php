<?php

namespace App\Services\Gmail;

class ThreadTicketDraftGenerator
{
    /**
     * Boundary for a future AI provider. This deterministic implementation keeps
     * the pasted-thread workflow local and predictable for review and tests.
     */
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
        $requester = $participants[0] ?? null;
        $objective = 'Resolver el pedido descrito en la cadena de correo.';

        return [
            'project_name' => 'Sin clasificar',
            'requester' => $requester,
            'title' => $subject,
            'original_text' => $threadText,
            'context' => $threadText,
            'objective' => $objective,
            'priority' => 'normal',
            'summary' => $summary !== '' ? $summary : $subject,
            'functional_expectations' => $expectations,
            'open_questions' => $questions,
        ];
    }

    /**
     * Exact future provider prompt. The provider must return the same keys as generate().
     */
    public function prompt(string $subject, array $participants, string $threadText): string
    {
        return "Analizá la cadena completa de Gmail y devolvé JSON válido con: project_name, requester, title, original_text, context, objective, priority (low|normal|high|urgent), summary, functional_expectations (array) y open_questions (array).\n\n"
            ."Asunto: {$subject}\nParticipantes: ".implode(', ', $participants)."\n\nCadena completa:\n{$threadText}\n\n"
            .'Conservá los hechos y requisitos explícitos. No inventes decisiones; convertí ambigüedades en open_questions.';
    }
}
