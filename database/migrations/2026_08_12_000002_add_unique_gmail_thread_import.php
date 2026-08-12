<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_thread_imports', function (Blueprint $table) {
            $table->unique(['provider', 'external_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::table('email_thread_imports', function (Blueprint $table) {
            $table->dropUnique(['provider', 'external_thread_id']);
        });
    }
};
