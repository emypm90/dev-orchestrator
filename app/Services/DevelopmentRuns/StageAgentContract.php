<?php

namespace App\Services\DevelopmentRuns;

class StageAgentContract
{
    /**
     * @param list<string> $inputs
     * @param list<string> $allowedActions
     * @param list<string> $outputs
     */
    public function render(string $stage, string $agent, string $objective, array $inputs, array $allowedActions, array $outputs): string
    {
        return "Contrato de agente de etapa\n"
            ."- Etapa: {$stage}\n"
            ."- Agente: {$agent}\n"
            ."- Objetivo: {$objective}\n\n"
            ."Inputs esperados\n"
            .$this->lines($inputs)
            ."\nAcciones permitidas\n"
            .$this->lines($allowedActions)
            ."\nOutput obligatorio\n"
            .$this->lines($outputs)
            ."\nReglas globales\n"
            ."- No stagear, commitear, pushear ni cambiar remotos.\n"
            ."- No borrar artifacts previos.\n"
            ."- Si falta información crítica, devolver blocked con motivo concreto.\n"
            ."- El orquestador decide la transición de etapa a partir del artifact devuelto.";
    }

    /**
     * @param list<string> $items
     */
    private function lines(array $items): string
    {
        return collect($items)
            ->map(fn (string $item) => "- {$item}")
            ->implode("\n")
            ."\n";
    }
}
