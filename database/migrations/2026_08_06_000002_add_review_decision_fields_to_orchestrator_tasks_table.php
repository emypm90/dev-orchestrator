<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->string('review_decision')->nullable()->after('last_verification_path');
            $table->timestamp('reviewed_at')->nullable()->after('review_decision');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->dropColumn(['review_decision', 'reviewed_at', 'review_notes']);
        });
    }
};
