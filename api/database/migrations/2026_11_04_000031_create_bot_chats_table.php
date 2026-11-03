<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Groups and channels the bot has been added to, so an admin picks the
        // questions group or jobs channel from a list instead of hunting for ids.
        Schema::create('bot_chats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->string('type', 16);
            $table->string('title', 160)->nullable();
            $table->string('username', 64)->nullable();
            $table->boolean('is_forum')->default(false);
            $table->string('bot_status', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_chats');
    }
};
