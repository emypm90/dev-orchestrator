<?php

namespace App\Services\ContextIngestion;

use App\Jobs\IngestContextAttachment;
use App\Models\ContextAttachment;
use App\Models\DevelopmentRun;
use App\Models\OrchestratorProject;
use Illuminate\Http\UploadedFile;

class ContextAttachmentService
{
    public const MAX_UPLOAD_KIB = 2048;

    /** @return array<string, mixed> */
    public static function validationRules(bool $multiple = false): array
    {
        $fileRule = ['file', 'max:'.self::MAX_UPLOAD_KIB, 'mimes:txt,md,markdown,csv,xlsx,pptx,mp3,wav,m4a,mp4,mov,webm'];

        return $multiple
            ? ['context_attachments' => ['nullable', 'array', 'max:5'], 'context_attachments.*' => $fileRule]
            : ['context_attachment' => ['required', ...$fileRule]];
    }

    public function storeUploaded(UploadedFile $file, OrchestratorProject $project, ?DevelopmentRun $run = null): ContextAttachment
    {
        $path = $file->store('context-attachments', 'local');

        $attachment = ContextAttachment::create([
            'orchestrator_project_id' => $project->id,
            'development_run_id' => $run?->id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'status' => ContextAttachment::STATUS_UPLOADED,
        ]);

        IngestContextAttachment::dispatch($attachment->id);

        return $attachment;
    }
}
