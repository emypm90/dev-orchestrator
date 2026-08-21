<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentRun extends Model
{
    protected $fillable = [
        'title', 'initial_context', 'repository', 'project', 'project_id', 'status', 'active_stage',
        'priority', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(DevelopmentRunArtifact::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(OrchestratorProject::class, 'project_id');
    }

    public function projectModel(): BelongsTo
    {
        return $this->project();
    }

    public function contextAttachments(): HasMany
    {
        return $this->hasMany(ContextAttachment::class);
    }

    public function contextDocuments(): HasMany
    {
        return $this->hasMany(ContextDocument::class);
    }
}
