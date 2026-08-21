<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrchestratorProject extends Model
{
    protected $table = 'orchestrator_projects';

    protected $fillable = [
        'name', 'repo_path', 'default_branch', 'test_command', 'lint_command', 'rules',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(OrchestratorTask::class, 'project_id');
    }

    public function developmentRuns(): HasMany
    {
        return $this->hasMany(DevelopmentRun::class, 'project_id');
    }

    public function contextAttachments(): HasMany
    {
        return $this->hasMany(ContextAttachment::class, 'orchestrator_project_id');
    }

    public function contextDocuments(): HasMany
    {
        return $this->hasMany(ContextDocument::class, 'orchestrator_project_id');
    }
}
