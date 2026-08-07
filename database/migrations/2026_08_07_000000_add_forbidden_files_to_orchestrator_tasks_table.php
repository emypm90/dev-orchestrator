<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->json('forbidden_files')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orchestrator_tasks', function (Blueprint $table): void {
            $table->dropColumn('forbidden_files');
        });
    }
};
