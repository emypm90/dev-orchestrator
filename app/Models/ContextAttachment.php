<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContextAttachment extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_TRANSCRIBING = 'transcribing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'orchestrator_project_id', 'development_run_id', 'original_name', 'storage_path',
        'mime_type', 'size_bytes', 'status', 'status_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(OrchestratorProject::class, 'orchestrator_project_id');
    }

    public function developmentRun(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRun::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContextDocument::class);
    }
}
