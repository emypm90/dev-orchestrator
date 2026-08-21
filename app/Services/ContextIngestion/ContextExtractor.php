<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;

interface ContextExtractor
{
    public function supports(ContextAttachment $attachment): bool;

    public function extract(ContextAttachment $attachment): ExtractedContext;
}
