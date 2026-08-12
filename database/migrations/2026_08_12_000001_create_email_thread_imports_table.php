<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_thread_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('gmail');
            $table->string('external_thread_id')->nullable();
            $table->string('subject');
            $table->json('participants')->nullable();
            $table->text('raw_thread_text');
            $table->text('ai_summary')->nullable();
            $table->json('ai_expectations')->nullable();
            $table->json('ai_questions')->nullable();
            $table->json('proposed_ticket_payload')->nullable();
            $table->foreignId('operational_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_thread_imports');
    }
};
