<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where each chat is in a multi-step flow (onboarding now, any
        // command with questions later). One row per Telegram user; it exists
        // before the member does, because a chat has to share a contact card
        // to become a member at all.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->unsignedBigInteger('chat_id');
            $table->string('language', 2)->default('am');
            $table->string('state', 32)->default('idle');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
