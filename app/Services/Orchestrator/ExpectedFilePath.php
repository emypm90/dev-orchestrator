<?php

namespace App\Services\Orchestrator;

use InvalidArgumentException;

class ExpectedFilePath
{
    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^(\./)+#', '', $path) ?? '';

        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1 || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('Expected file paths must be relative to the task directory.');
        }

        if (in_array('..', explode('/', $path), true)) {
            throw new InvalidArgumentException('Expected file paths cannot contain ".." traversal segments.');
        }

        return $path;
    }
}
