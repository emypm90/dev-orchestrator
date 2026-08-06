<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orchestrator_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('repo_path')->unique();
            $table->string('default_branch')->default('main');
            $table->text('test_command')->nullable();
            $table->text('lint_command')->nullable();
            $table->text('rules')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orchestrator_projects');
    }
};
