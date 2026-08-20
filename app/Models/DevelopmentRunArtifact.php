<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentRunArtifact extends Model
{
    protected $fillable = [
        'development_run_id', 'type', 'title', 'body', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function developmentRun(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRun::class);
    }
}
