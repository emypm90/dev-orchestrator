<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('email_address');
            $table->string('display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('status')->default('disconnected');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'email_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
