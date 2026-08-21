<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextDocument extends Model
{
    protected $fillable = [
        'context_attachment_id', 'orchestrator_project_id', 'development_run_id',
        'source_label', 'body', 'summary', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ContextAttachment::class, 'context_attachment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(OrchestratorProject::class, 'orchestrator_project_id');
    }

    public function developmentRun(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRun::class);
    }
}
