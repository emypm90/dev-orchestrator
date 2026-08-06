<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrchestratorTask extends Model
{
    protected $table = 'orchestrator_tasks';

    protected $fillable = [
        'project_id', 'title', 'description', 'acceptance_criteria', 'autonomy', 'status',
        'branch_name', 'worktree_path', 'last_exit_code', 'prepared_at', 'started_at', 'finished_at',
        'archived_at', 'archive_path', 'worktree_removed_at', 'latest_commit_hash',
        'last_verification_status', 'last_verified_at', 'last_verification_path',
    ];

    protected function casts(): array
    {
        return [
            'prepared_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'archived_at' => 'datetime',
            'worktree_removed_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(OrchestratorProject::class, 'project_id');
    }
}
