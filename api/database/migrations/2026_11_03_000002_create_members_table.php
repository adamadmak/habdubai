<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->string('telegram_username', 64)->nullable();
            // The number itself is never stored: an HMAC of its digits plus
            // the last four, enough to find the same person again and to show
            // a moderator "…5678" without ever showing the number.
            $table->string('phone_hash', 64)->unique();
            $table->string('phone_last4', 4);
            $table->string('phone_country', 4);
            $table->boolean('needs_review')->default(false);
            $table->string('display_name', 80);
            $table->string('emirate', 32)->nullable();
            $table->string('language', 2)->default('am');
            $table->string('role', 16)->default('member');
            $table->timestamp('verified_at');
            $table->timestamp('agreed_rules_at')->nullable();
            $table->unsignedInteger('helper_points')->default(0);
            $table->unsignedInteger('trips_completed')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('left_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->string('ban_reason')->nullable();
            $table->timestamps();

            $table->index(['role']);
            $table->index(['agreed_rules_at']);
        });

        Schema::create('member_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('trade', 32);
            $table->unique(['member_id', 'trade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_trades');
        Schema::dropIfExists('members');
    }
};
