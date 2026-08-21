<?php

namespace Tests\Feature;

use App\Jobs\IngestContextAttachment;
use App\Models\ContextAttachment;
use App\Models\DevelopmentRun;
use App\Models\OrchestratorProject;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_project_text_upload_is_stored_privately_as_uploaded_and_queued_for_ingestion(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Reglas reutilizables.',
        ]);

        $response = $this->post(route('projects.context-attachments.store', $project), [
            'context_attachment' => UploadedFile::fake()->createWithContent('architecture-notes.md', "# Arquitectura\nUsar servicios chicos."),
        ]);

        $attachment = ContextAttachment::firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertTrue($attachment->project->is($project));
        $this->assertNull($attachment->development_run_id);
        $this->assertSame(ContextAttachment::STATUS_UPLOADED, $attachment->status);
        $this->assertSame('architecture-notes.md', $attachment->original_name);
        $this->assertStringStartsWith('context-attachments/', $attachment->storage_path);
        Storage::disk('local')->assertExists($attachment->storage_path);
        Queue::assertPushed(IngestContextAttachment::class, fn (IngestContextAttachment $job) => $job->attachmentId === $attachment->id);
    }

    public function test_project_upload_accepts_rich_documents_and_media_for_bounded_async_ingestion(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
        ]);

        $files = [
            $this->upload('scope.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            $this->upload('roadmap.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
            $this->upload('standup.mp3', 'audio/mpeg'),
            $this->upload('demo.mp4', 'video/mp4'),
        ];

        foreach ($files as $file) {
            $this->post(route('projects.context-attachments.store', $project), [
                'context_attachment' => $file,
            ])->assertRedirect(route('projects.show', $project));
        }

        $attachments = ContextAttachment::query()->orderBy('id')->get();

        $this->assertSame(['scope.xlsx', 'roadmap.pptx', 'standup.mp3', 'demo.mp4'], array_map(
            fn (ContextAttachment $attachment): string => $attachment->original_name,
            iterator_to_array($attachments),
        ));

        foreach ($attachments as $attachment) {
            $this->assertTrue($attachment->project->is($project));
            $this->assertSame(ContextAttachment::STATUS_UPLOADED, $attachment->status);
            $this->assertStringStartsWith('context-attachments/', $attachment->storage_path);
            Storage::disk('local')->assertExists($attachment->storage_path);
            Queue::assertPushed(IngestContextAttachment::class, fn (IngestContextAttachment $job) => $job->attachmentId === $attachment->id);
        }
    }

    public function test_project_upload_rejects_unsupported_file_types_without_storage_or_queue(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
        ]);

        $this->from(route('projects.show', $project))->post(route('projects.context-attachments.store', $project), [
            'context_attachment' => UploadedFile::fake()->createWithContent('malware.exe', 'not context'),
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseCount('context_attachments', 0);
        Storage::disk('local')->assertMissing('context-attachments/malware.exe');
        Queue::assertNothingPushed();
    }

    public function test_nested_run_creation_accepts_run_attachment_as_uploaded_processing_context(): void
    {
        $project = OrchestratorProject::create([
            'name' => 'Command Flow',
            'repo_path' => 'C:\\work\\command-flow',
            'rules' => 'Reglas reutilizables.',
        ]);

        $response = $this->post(route('projects.development-runs.store', $project), [
            'title' => 'Agregar adjuntos de contexto',
            'initial_context' => 'Contexto manual mínimo.',
            'context_attachments' => [
                UploadedFile::fake()->createWithContent('task-notes.txt', 'Nota específica del run.'),
            ],
        ]);

        $run = DevelopmentRun::firstOrFail();
        $attachment = ContextAttachment::firstOrFail();

        $response->assertRedirect(route('development-runs.show', $run));
        $this->assertTrue($attachment->project->is($project));
        $this->assertTrue($attachment->developmentRun->is($run));
        $this->assertSame(ContextAttachment::STATUS_UPLOADED, $attachment->status);
        Queue::assertPushed(IngestContextAttachment::class, fn (IngestContextAttachment $job) => $job->attachmentId === $attachment->id);
    }

    private function upload(string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'context-upload-');
        file_put_contents($path, $name.' placeholder');

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
