<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;

interface TranscriptionProvider
{
    public function transcribe(ContextAttachment $attachment): ExtractedContext;
}
