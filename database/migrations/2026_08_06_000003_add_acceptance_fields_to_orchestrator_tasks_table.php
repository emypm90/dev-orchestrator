<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->json('expected_files')->nullable();
            $table->string('last_acceptance_status')->nullable();
            $table->timestamp('last_acceptance_checked_at')->nullable();
            $table->text('last_acceptance_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->dropColumn(['expected_files', 'last_acceptance_status', 'last_acceptance_checked_at', 'last_acceptance_path']);
        });
    }
};
