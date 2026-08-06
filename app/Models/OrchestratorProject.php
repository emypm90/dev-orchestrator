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
}
