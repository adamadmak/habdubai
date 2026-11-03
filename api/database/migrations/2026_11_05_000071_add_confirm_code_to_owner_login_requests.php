<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The sign-in code now travels the other way: after "Yes, sign me in" the bot
 * sends a six-digit code that must be typed on the website. Someone who sends
 * an owner a sign-in link of their own never sees it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_login_requests', function (Blueprint $table) {
            $table->string('confirm_code_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('owner_login_requests', function (Blueprint $table) {
            $table->dropColumn(['confirm_code_hash', 'attempts']);
        });
    }
};
