<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_tickets', function (Blueprint $table): void {
            $table->foreignId('orchestrator_task_id')
                ->nullable()
                ->unique()
                ->constrained('orchestrator_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operational_tickets', function (Blueprint $table): void {
            $table->dropForeign(['orchestrator_task_id']);
            $table->dropUnique(['orchestrator_task_id']);
            $table->dropColumn('orchestrator_task_id');
        });
    }
};
