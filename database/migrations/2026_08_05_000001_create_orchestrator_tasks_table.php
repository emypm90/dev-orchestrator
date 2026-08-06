<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orchestrator_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('orchestrator_projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->string('autonomy')->default('medium');
            $table->string('status')->default('draft');
            $table->string('branch_name')->nullable()->unique();
            $table->text('worktree_path')->nullable()->unique();
            $table->integer('last_exit_code')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orchestrator_tasks');
    }
};
