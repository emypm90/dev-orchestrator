<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;

class OfficeDocumentExtractor implements BlockingContextExtractor
{
    private const EXTENSIONS = ['xlsx', 'pptx'];

    public function supports(ContextAttachment $attachment): bool
    {
        return in_array(strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)), self::EXTENSIONS, true)
            || in_array($attachment->mime_type, [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ], true);
    }

    public function extract(ContextAttachment $attachment): ExtractedContext
    {
        return new ExtractedContext('', '', ['extractor' => self::class]);
    }

    public function blockingReason(): string
    {
        return 'Este archivo requiere un extractor configurado para documentos XLSX/PPTX antes de poder usarse como contexto.';
    }

    public function processingStatus(): string
    {
        return ContextAttachment::STATUS_EXTRACTING;
    }
}
