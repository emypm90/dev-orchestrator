<?php

namespace Tests\Unit;

use App\Models\DevelopmentRun;
use App\Models\OrchestratorProject;
use App\Services\DevelopmentRuns\ProjectContextAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectContextAssemblerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assembles_manual_project_and_run_context_with_source_labels_and_caps(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'ABCDEFGHIJ reglas de arquitectura del proyecto que no deben entrar completas.',
        ]);
        $run = DevelopmentRun::create([
            'project_id' => $project->id,
            'title' => 'Slice chico',
            'initial_context' => '1234567890 contexto específico de tarea que tampoco entra completo.',
            'repository' => $project->repo_path,
            'project' => $project->name,
            'started_at' => now(),
        ]);

        $context = app(ProjectContextAssembler::class)->forRun($run, [
            'total' => 140,
            'project' => 24,
            'run' => 22,
        ]);

        $this->assertStringContainsString('[project:Command Flow]', $context);
        $this->assertStringContainsString('ABCDEFGHIJ reglas de ar…', $context);
        $this->assertStringContainsString('[run:contexto-inicial]', $context);
        $this->assertStringContainsString('1234567890 contexto e…', $context);
        $this->assertStringNotContainsString('deben entrar completas', $context);
        $this->assertLessThanOrEqual(140, mb_strlen($context));
    }

    public function test_it_omits_attachment_like_artifacts_until_they_are_ready(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Contexto manual listo.',
        ]);
        $run = DevelopmentRun::create([
            'project_id' => $project->id,
            'title' => 'Slice chico',
            'initial_context' => 'Contexto de tarea listo.',
            'repository' => $project->repo_path,
            'project' => $project->name,
            'started_at' => now(),
        ]);
        $run->artifacts()->create([
            'type' => 'context_document',
            'title' => 'Documento pendiente',
            'body' => 'ESTE TEXTO PENDIENTE NO DEBE APARECER',
            'metadata' => ['status' => 'uploaded', 'source' => 'attachment'],
        ]);
        $run->artifacts()->create([
            'type' => 'context_document',
            'title' => 'Documento listo',
            'body' => 'Extracto listo y acotado.',
            'metadata' => ['status' => 'ready', 'source' => 'manual-note'],
        ]);

        $context = app(ProjectContextAssembler::class)->forRun($run, ['total' => 500]);

        $this->assertStringContainsString('[project:Command Flow]', $context);
        $this->assertStringContainsString('[run:contexto-inicial]', $context);
        $this->assertStringContainsString('[run:Documento listo]', $context);
        $this->assertStringContainsString('Extracto listo y acotado.', $context);
        $this->assertStringNotContainsString('ESTE TEXTO PENDIENTE NO DEBE APARECER', $context);
    }
}
