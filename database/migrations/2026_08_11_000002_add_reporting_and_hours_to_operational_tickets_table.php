<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_tickets', function (Blueprint $table): void {
            $table->text('report_message')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->decimal('hours_estimate', 5, 2)->nullable();
            $table->text('hours_notes')->nullable();
            $table->timestamp('hours_recorded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('operational_tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'report_message',
                'reported_at',
                'hours_estimate',
                'hours_notes',
                'hours_recorded_at',
            ]);
        });
    }
};
