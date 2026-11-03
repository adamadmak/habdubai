<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A question is asked in the bot and answered in the questions group,
        // in its own forum topic when the group has topics turned on.
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->boolean('anonymous')->default(false);
            $table->string('category', 16);
            $table->string('title', 100);
            $table->text('body');
            $table->bigInteger('group_chat_id')->nullable();
            $table->bigInteger('group_message_id')->nullable();
            $table->bigInteger('forum_topic_id')->nullable()->index();
            // open → solved; either → closed after seven quiet days
            $table->string('status', 8)->default('open')->index();
            $table->foreignId('solved_reply_id')->nullable();
            $table->unsignedBigInteger('faq_id')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('question_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('telegram_message_id');
            $table->text('text');
            $table->unsignedTinyInteger('points_awarded')->default(0);
            $table->timestamp('created_at');

            $table->unique(['question_id', 'telegram_message_id']);
        });

        Schema::create('faq_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 16);
            $table->text('q_am')->nullable();
            $table->text('q_ti')->nullable();
            $table->text('q_en');
            $table->text('a_am')->nullable();
            $table->text('a_ti')->nullable();
            $table->text('a_en');
            $table->string('source_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // What the assistant may answer from, beside the FAQ: published rules
        // an admin has checked (MoHRE, GDRFA, the consulate), in plain English.
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('source_name', 120)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // The assistant keeps the last ten turns per member and nothing older.
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('role', 9);
            $table->text('text');
            $table->timestamp('created_at')->index();
        });

        // The safety desk's private form. Never joined to anything public.
        Schema::create('employer_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employer_name', 160);
            $table->text('text');
            $table->boolean('contactable')->default(false);
            $table->string('status', 8)->default('open')->index();
            $table->text('note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_reports');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('faq_entries');
        Schema::dropIfExists('question_replies');
        Schema::dropIfExists('questions');
    }
};
