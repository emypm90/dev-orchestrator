<?php

namespace Tests\Unit;

use App\Jobs\IngestContextAttachment;
use App\Models\ContextAttachment;
use App\Models\DevelopmentRun;
use App\Models\OrchestratorProject;
use App\Services\ContextIngestion\OfficeDocumentExtractor;
use App\Services\DevelopmentRuns\ProjectContextAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_text_ingestion_marks_attachment_ready_and_creates_source_labeled_context_document(): void
    {
        $project = OrchestratorProject::create(['name' => 'Command Flow', 'repo_path' => 'C:\\work\\command-flow']);
        $run = DevelopmentRun::create([
            'project_id' => $project->id,
            'title' => 'Slice chico',
            'initial_context' => 'Contexto manual.',
            'repository' => $project->repo_path,
            'project' => $project->name,
            'started_at' => now(),
        ]);
        Storage::disk('local')->put('context-attachments/notes.txt', "Primera línea útil.\nSegunda línea útil.");
        $attachment = ContextAttachment::create([
            'orchestrator_project_id' => $project->id,
            'development_run_id' => $run->id,
            'original_name' => 'notes.txt',
            'storage_path' => 'context-attachments/notes.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 36,
            'status' => ContextAttachment::STATUS_UPLOADED,
        ]);

        (new IngestContextAttachment($attachment->id))->handle();

        $attachment->refresh();
        $document = $attachment->documents()->firstOrFail();
        $this->assertSame(ContextAttachment::STATUS_READY, $attachment->status);
        $this->assertSame('notes.txt', $document->source_label);
        $this->assertStringContainsString('Primera línea útil.', $document->body);
        $this->assertStringContainsString('[run:notes.txt]', app(ProjectContextAssembler::class)->forRun($run->fresh()));
    }

    public function test_csv_ingestion_normalizes_rows_without_dumping_unbounded_csv(): void
    {
        $project = OrchestratorProject::create(['name' => 'Command Flow', 'repo_path' => 'C:\\work\\command-flow']);
        Storage::disk('local')->put('context-attachments/risks.csv', "risk,owner\nPrompt bloat,Architecture\nQueue stopped,Ops\nIgnored row,Later");
        $attachment = ContextAttachment::create([
            'orchestrator_project_id' => $project->id,
            'original_name' => 'risks.csv',
            'storage_path' => 'context-attachments/risks.csv',
            'mime_type' => 'text/csv',
            'size_bytes' => 70,
            'status' => ContextAttachment::STATUS_UPLOADED,
        ]);

        (new IngestContextAttachment($attachment->id))->handle();

        $body = $attachment->documents()->firstOrFail()->body;
        $this->assertStringContainsString('risk: Prompt bloat | owner: Architecture', $body);
        $this->assertStringContainsString('risk: Queue stopped | owner: Ops', $body);
        $this->assertStringNotContainsString('Ignored row', $body);
    }

    public function test_unsupported_ingestion_marks_attachment_blocked_with_visible_reason(): void
    {
        $project = OrchestratorProject::create(['name' => 'Command Flow', 'repo_path' => 'C:\\work\\command-flow']);
        Storage::disk('local')->put('context-attachments/archive.zip', 'binary');
        $attachment = ContextAttachment::create([
            'orchestrator_project_id' => $project->id,
            'original_name' => 'archive.zip',
            'storage_path' => 'context-attachments/archive.zip',
            'mime_type' => 'application/zip',
            'size_bytes' => 6,
            'status' => ContextAttachment::STATUS_UPLOADED,
        ]);

        (new IngestContextAttachment($attachment->id))->handle();

        $attachment->refresh();
        $this->assertSame(ContextAttachment::STATUS_BLOCKED, $attachment->status);
        $this->assertSame('No hay extractor configurado para application/zip.', $attachment->status_reason);
        $this->assertDatabaseCount('context_documents', 0);
    }

    public function test_xlsx_and_pptx_ingestion_are_blocked_without_dumping_binary_content_into_documents(): void
    {
        $project = OrchestratorProject::create(['name' => 'Command Flow', 'repo_path' => 'C:\\work\\command-flow']);

        foreach ([
            ['scope.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['roadmap.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        ] as [$name, $mime]) {
            Storage::disk('local')->put('context-attachments/'.$name, 'PK fake office binary content');
            $attachment = ContextAttachment::create([
                'orchestrator_project_id' => $project->id,
                'original_name' => $name,
                'storage_path' => 'context-attachments/'.$name,
                'mime_type' => $mime,
                'size_bytes' => 24,
                'status' => ContextAttachment::STATUS_UPLOADED,
            ]);

            (new IngestContextAttachment($attachment->id))->handle();

            $attachment->refresh();
            $this->assertSame(ContextAttachment::STATUS_BLOCKED, $attachment->status);
            $this->assertStringContainsString('requiere un extractor configurado', $attachment->status_reason);
            $this->assertDatabaseMissing('context_documents', ['context_attachment_id' => $attachment->id]);
        }

        $this->assertTrue(class_exists(OfficeDocumentExtractor::class));
    }

    public function test_audio_and_video_require_transcription_and_are_omitted_from_assembled_context_until_ready(): void
    {
        $project = OrchestratorProject::create(['name' => 'Command Flow', 'repo_path' => 'C:\\work\\command-flow', 'rules' => 'Reglas del proyecto.']);
        $run = DevelopmentRun::create([
            'project_id' => $project->id,
            'title' => 'Analizar demo',
            'initial_context' => 'Contexto manual seguro.',
            'repository' => $project->repo_path,
            'project' => $project->name,
            'started_at' => now(),
        ]);

        foreach ([
            ['standup.mp3', 'audio/mpeg', 'audio bruto con secretos'],
            ['demo.mp4', 'video/mp4', 'video bruto con secretos'],
        ] as [$name, $mime, $rawContent]) {
            Storage::disk('local')->put('context-attachments/'.$name, $rawContent);
            $attachment = ContextAttachment::create([
                'orchestrator_project_id' => $project->id,
                'development_run_id' => $run->id,
                'original_name' => $name,
                'storage_path' => 'context-attachments/'.$name,
                'mime_type' => $mime,
                'size_bytes' => 24,
                'status' => ContextAttachment::STATUS_UPLOADED,
            ]);

            (new IngestContextAttachment($attachment->id))->handle();

            $attachment->refresh();
            $this->assertSame(ContextAttachment::STATUS_BLOCKED, $attachment->status);
            $this->assertStringContainsString('transcripción', $attachment->status_reason);
            $this->assertDatabaseMissing('context_documents', ['context_attachment_id' => $attachment->id]);
        }

        $context = app(ProjectContextAssembler::class)->forRun($run->fresh());

        $this->assertStringContainsString('Contexto manual seguro.', $context);
        $this->assertStringNotContainsString('audio bruto con secretos', $context);
        $this->assertStringNotContainsString('video bruto con secretos', $context);
    }

    public function test_rich_extensions_are_blocked_before_generic_text_mime_extraction(): void
    {
        $project = OrchestratorProject::create(['name' => 'Command Flow', 'repo_path' => 'C:\\work\\command-flow']);
        $run = DevelopmentRun::create([
            'project_id' => $project->id,
            'title' => 'Analizar adjuntos engañosos',
            'initial_context' => 'Contexto manual seguro.',
            'repository' => $project->repo_path,
            'project' => $project->name,
            'started_at' => now(),
        ]);

        foreach ([
            ['budget.xlsx', 'planilla cruda que no debe entrar al prompt', 'requiere un extractor configurado'],
            ['demo.mp4', 'video crudo que no debe entrar al prompt', 'transcripción'],
        ] as [$name, $rawContent, $reason]) {
            Storage::disk('local')->put('context-attachments/'.$name, $rawContent);
            $attachment = ContextAttachment::create([
                'orchestrator_project_id' => $project->id,
                'development_run_id' => $run->id,
                'original_name' => $name,
                'storage_path' => 'context-attachments/'.$name,
                'mime_type' => 'text/plain',
                'size_bytes' => strlen($rawContent),
                'status' => ContextAttachment::STATUS_UPLOADED,
            ]);

            (new IngestContextAttachment($attachment->id))->handle();

            $attachment->refresh();
            $this->assertSame(ContextAttachment::STATUS_BLOCKED, $attachment->status);
            $this->assertStringContainsString($reason, $attachment->status_reason);
            $this->assertDatabaseMissing('context_documents', ['context_attachment_id' => $attachment->id]);
        }

        $context = app(ProjectContextAssembler::class)->forRun($run->fresh());

        $this->assertStringContainsString('Contexto manual seguro.', $context);
        $this->assertStringNotContainsString('planilla cruda que no debe entrar al prompt', $context);
        $this->assertStringNotContainsString('video crudo que no debe entrar al prompt', $context);
    }
}
