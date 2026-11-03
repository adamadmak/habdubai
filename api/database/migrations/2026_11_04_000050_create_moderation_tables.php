<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('muted_until')->nullable()->after('banned_at');
        });

        // A member's /report on a message in a community group.
        Schema::create('group_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('members')->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id');
            $table->foreignId('reported_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('excerpt');
            // open → closed (by a moderator), or hidden (three reports took it down) then closed
            $table->string('status', 8)->default('open')->index();
            $table->string('action', 16)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['reporter_id', 'chat_id', 'message_id']);
            $table->index(['chat_id', 'message_id']);
        });

        // Every automatic removal, warning and mute, to count repeats.
        Schema::create('member_strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('reason', 250);
            $table->bigInteger('chat_id')->nullable();
            $table->timestamp('until')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_strikes');
        Schema::dropIfExists('group_reports');
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('muted_until');
        });
    }
};
