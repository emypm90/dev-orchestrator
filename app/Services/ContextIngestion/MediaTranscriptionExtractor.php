<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;

class MediaTranscriptionExtractor implements BlockingContextExtractor
{
    public function supports(ContextAttachment $attachment): bool
    {
        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));

        return str_starts_with((string) $attachment->mime_type, 'audio/')
            || str_starts_with((string) $attachment->mime_type, 'video/')
            || in_array($extension, ['mp3', 'wav', 'm4a', 'mp4', 'mov', 'webm'], true);
    }

    public function extract(ContextAttachment $attachment): ExtractedContext
    {
        return new ExtractedContext('', '', ['extractor' => self::class]);
    }

    public function blockingReason(): string
    {
        return 'Este archivo requiere transcripción configurada antes de poder usarse como contexto.';
    }

    public function processingStatus(): string
    {
        return ContextAttachment::STATUS_TRANSCRIBING;
    }
}
