<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('finished_at');
            $table->text('archive_path')->nullable()->after('archived_at');
            $table->timestamp('worktree_removed_at')->nullable()->after('archive_path');
            $table->string('latest_commit_hash')->nullable()->after('worktree_removed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->dropColumn(['archived_at', 'archive_path', 'worktree_removed_at', 'latest_commit_hash']);
        });
    }
};
