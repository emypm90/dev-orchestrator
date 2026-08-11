<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('source')->default('manual');
            $table->string('requester')->nullable();
            $table->string('title');
            $table->text('original_text');
            $table->text('objective')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('inbox');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_tickets');
    }
};
