<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->string('last_verification_status')->nullable()->after('latest_commit_hash');
            $table->timestamp('last_verified_at')->nullable()->after('last_verification_status');
            $table->text('last_verification_path')->nullable()->after('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->dropColumn(['last_verification_status', 'last_verified_at', 'last_verification_path']);
        });
    }
};
