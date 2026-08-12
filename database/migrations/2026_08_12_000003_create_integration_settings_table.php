<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('google_oauth_client_id')->nullable();
            $table->text('google_oauth_client_secret')->nullable();
            $table->string('google_oauth_redirect_uri')->nullable();
            $table->text('openai_api_key')->nullable();
            $table->string('openai_ticket_draft_model')->nullable();
            $table->boolean('openai_ticket_draft_enabled')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
