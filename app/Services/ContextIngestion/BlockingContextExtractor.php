<?php

namespace App\Services\ContextIngestion;

interface BlockingContextExtractor extends ContextExtractor
{
    public function blockingReason(): string;

    public function processingStatus(): string;
}
