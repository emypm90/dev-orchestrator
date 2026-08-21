<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;
use Throwable;

class ContextAttachmentIngestor
{
    /** @var list<ContextExtractor> */
    private array $extractors;

    public function __construct()
    {
        $this->extractors = [new OfficeDocumentExtractor, new MediaTranscriptionExtractor, new CsvExtractor, new PlainTextExtractor];
    }

    public function ingest(ContextAttachment $attachment): void
    {
        $extractor = collect($this->extractors)->first(fn (ContextExtractor $candidate) => $candidate->supports($attachment));

        if (! $extractor) {
            $attachment->update([
                'status' => ContextAttachment::STATUS_BLOCKED,
                'status_reason' => 'No hay extractor configurado para '.$attachment->mime_type.'.',
                'processed_at' => now(),
            ]);

            return;
        }

        $attachment->update([
            'status' => $extractor instanceof BlockingContextExtractor ? $extractor->processingStatus() : ContextAttachment::STATUS_EXTRACTING,
            'status_reason' => null,
        ]);

        if ($extractor instanceof BlockingContextExtractor) {
            $attachment->update([
                'status' => ContextAttachment::STATUS_BLOCKED,
                'status_reason' => $extractor->blockingReason(),
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $extracted = $extractor->extract($attachment);

            $attachment->documents()->create([
                'orchestrator_project_id' => $attachment->orchestrator_project_id,
                'development_run_id' => $attachment->development_run_id,
                'source_label' => $attachment->original_name,
                'body' => $extracted->body,
                'summary' => $extracted->summary,
                'metadata' => [...$extracted->metadata, 'status' => ContextAttachment::STATUS_READY],
            ]);

            $attachment->update(['status' => ContextAttachment::STATUS_READY, 'status_reason' => null, 'processed_at' => now()]);
        } catch (Throwable $exception) {
            $attachment->update([
                'status' => ContextAttachment::STATUS_FAILED,
                'status_reason' => $exception->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }
}
