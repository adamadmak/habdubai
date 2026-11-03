<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin overrides of the bot's wording. The defaults live in
        // resources/bot/texts.php; a row here wins for its key and language.
        Schema::create('bot_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->string('language', 2);
            $table->text('text');
            $table->timestamps();

            $table->unique(['key', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_texts');
    }
};
