<?php

namespace App\Services\DevelopmentRuns;

use App\Models\DevelopmentRun;

class ProjectContextAssembler
{
    /**
     * @param  array{total?: int, project?: int, run?: int, document?: int}  $caps
     */
    public function forRun(DevelopmentRun $run, array $caps = []): string
    {
        $limits = [
            'total' => (int) ($caps['total'] ?? 4000),
            'project' => (int) ($caps['project'] ?? 1600),
            'run' => (int) ($caps['run'] ?? 1600),
            'document' => (int) ($caps['document'] ?? 900),
        ];

        $run->loadMissing(['projectModel.contextDocuments', 'contextDocuments', 'artifacts']);

        $sections = collect();
        $project = $run->projectModel;

        if ($project && filled($project->rules)) {
            $sections->push($this->section("project:{$project->name}", $project->rules, $limits['project']));
        }

        if (filled($run->initial_context)) {
            $sections->push($this->section('run:contexto-inicial', $run->initial_context, $limits['run']));
        }

        $run->artifacts
            ->filter(fn ($artifact) => $artifact->type === 'context_document' && data_get($artifact->metadata, 'status') === 'ready')
            ->each(fn ($artifact) => $sections->push($this->section("run:{$artifact->title}", $artifact->body, $limits['document'])));

        if ($project) {
            $project->contextDocuments
                ->whereNull('development_run_id')
                ->each(fn ($document) => $sections->push($this->section("project:{$document->source_label}", $document->body, $limits['document'])));
        }

        $run->contextDocuments
            ->each(fn ($document) => $sections->push($this->section("run:{$document->source_label}", $document->body, $limits['document'])));

        $context = $sections->filter()->implode("\n\n");

        return $this->limit($context, $limits['total']);
    }

    private function section(string $label, string $body, int $limit): string
    {
        return "[{$label}]\n".$this->limit($body, $limit);
    }

    private function limit(string $value, int $limit): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($limit <= 0 || mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, max(0, $limit - 1))).'…';
    }
}
