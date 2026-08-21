<?php

namespace App\Jobs;

use App\Models\ContextAttachment;
use App\Services\ContextIngestion\ContextAttachmentIngestor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestContextAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $attachmentId) {}

    public function handle(ContextAttachmentIngestor $ingestor = new ContextAttachmentIngestor): void
    {
        $attachment = ContextAttachment::findOrFail($this->attachmentId);

        $ingestor->ingest($attachment);
    }
}
