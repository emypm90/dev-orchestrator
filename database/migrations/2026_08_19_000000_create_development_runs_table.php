<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_runs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('initial_context');
            $table->string('repository')->nullable();
            $table->string('project')->nullable();
            $table->string('status')->default('intake');
            $table->string('active_stage')->default('contexto');
            $table->string('priority')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_runs');
    }
};
