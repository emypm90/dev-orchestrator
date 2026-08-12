<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_thread_imports', function (Blueprint $table) {
            $table->string('draft_generator')->nullable()->after('raw_thread_text');
        });
    }

    public function down(): void
    {
        Schema::table('email_thread_imports', function (Blueprint $table) {
            $table->dropColumn('draft_generator');
        });
    }
};
