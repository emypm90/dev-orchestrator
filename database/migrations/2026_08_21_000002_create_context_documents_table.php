<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('orchestrator_project_id')->constrained('orchestrator_projects')->cascadeOnDelete();
            $table->foreignId('development_run_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_label');
            $table->longText('body');
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['orchestrator_project_id', 'development_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_documents');
    }
};
