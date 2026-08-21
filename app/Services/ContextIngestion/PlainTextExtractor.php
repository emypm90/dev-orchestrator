<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;
use Illuminate\Support\Facades\Storage;

class PlainTextExtractor implements ContextExtractor
{
    public function supports(ContextAttachment $attachment): bool
    {
        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));

        return in_array($extension, ['txt', 'md', 'markdown'], true)
            || str_starts_with((string) $attachment->mime_type, 'text/plain')
            || in_array($attachment->mime_type, ['text/markdown'], true);
    }

    public function extract(ContextAttachment $attachment): ExtractedContext
    {
        $body = $this->normalize(Storage::disk('local')->get($attachment->storage_path));

        return new ExtractedContext(
            body: $body,
            summary: mb_substr($body, 0, 500),
            metadata: ['extractor' => self::class],
        );
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace("/\r\n?/", "\n", $value) ?? $value);
    }
}
