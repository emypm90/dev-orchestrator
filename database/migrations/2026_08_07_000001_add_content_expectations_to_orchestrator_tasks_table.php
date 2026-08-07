<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->json('expected_texts')->nullable();
            $table->json('expected_regexes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->dropColumn(['expected_texts', 'expected_regexes']);
        });
    }
};
