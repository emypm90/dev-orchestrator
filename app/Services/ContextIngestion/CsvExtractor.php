<?php

namespace App\Services\ContextIngestion;

use App\Models\ContextAttachment;
use Illuminate\Support\Facades\Storage;

class CsvExtractor implements ContextExtractor
{
    public function supports(ContextAttachment $attachment): bool
    {
        return strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) === 'csv'
            || in_array($attachment->mime_type, ['text/csv', 'application/csv'], true);
    }

    public function extract(ContextAttachment $attachment): ExtractedContext
    {
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim(Storage::disk('local')->get($attachment->storage_path))) ?: []);
        $headers = array_map(fn ($header) => trim((string) $header), $rows[0] ?? []);
        $sampleRows = array_slice($rows, 1, 2);

        $lines = array_map(function (array $row) use ($headers): string {
            $cells = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $cells[] = $header.': '.trim((string) ($row[$index] ?? ''));
            }

            return implode(' | ', $cells);
        }, $sampleRows);

        $body = implode("\n", array_filter($lines));

        return new ExtractedContext(
            body: $body,
            summary: 'CSV normalizado: '.count($sampleRows).' filas de muestra.',
            metadata: ['extractor' => self::class, 'sampled_rows' => count($sampleRows), 'total_rows' => max(0, count($rows) - 1)],
        );
    }
}
