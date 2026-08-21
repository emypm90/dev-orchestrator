<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orchestrator_project_id')->constrained('orchestrator_projects')->cascadeOnDelete();
            $table->foreignId('development_run_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status')->default('uploaded');
            $table->text('status_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['orchestrator_project_id', 'development_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_attachments');
    }
};
