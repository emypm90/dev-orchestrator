<?php

namespace App\Services\ContextIngestion;

class ExtractedContext
{
    public function __construct(
        public readonly string $body,
        public readonly string $summary,
        public readonly array $metadata = [],
    ) {}
}
