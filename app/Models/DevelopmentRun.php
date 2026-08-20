<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentRun extends Model
{
    protected $fillable = [
        'title', 'initial_context', 'repository', 'project', 'status', 'active_stage',
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
}
