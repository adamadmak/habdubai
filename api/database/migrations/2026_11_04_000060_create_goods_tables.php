<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The goods board: travellers with spare kilos and members who need something
 * carried between the UAE and Ethiopia. The platform holds no money and carries
 * nothing; it introduces two members and keeps the record that builds trust.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);            // traveller | sender
            $table->string('direction', 8);        // to_et | to_ae
            $table->date('date');                  // flight date, or the day the sender wants it to go
            $table->string('city_from', 40);
            $table->string('city_to', 40);
            $table->unsignedTinyInteger('kilos');  // spare kilos, or the parcel's weight
            $table->unsignedTinyInteger('kilos_left')->nullable(); // traveller only
            $table->json('categories');
            $table->string('description', 300)->nullable(); // sender: what is inside; traveller: conditions
            // Kept empty in phase one so a paid option can come later without migrating history.
            $table->unsignedInteger('price')->nullable();
            // open → matched | expired | closed | hidden
            $table->string('status', 8)->default('open')->index();
            $table->bigInteger('group_message_id')->nullable();
            $table->string('hidden_reason', 250)->nullable();
            $table->timestamps();

            $table->index(['kind', 'direction', 'status', 'date']);
        });

        // One member's interest in another's post, pairing the two posts, and everything after.
        Schema::create('goods_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('goods_posts')->cascadeOnDelete();
            $table->foreignId('requester_post_id')->nullable()->constrained('goods_posts')->nullOnDelete();
            $table->foreignId('owner_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('members')->cascadeOnDelete();
            // requested → introduced | declined | cancelled | expired
            // introduced → agreed | cancelled | expired
            // agreed → completed | disputed | cancelled | unconfirmed
            // disputed → completed | failed | cancelled (a moderator decides)
            $table->string('status', 12)->default('requested')->index();
            $table->unsignedTinyInteger('kilos')->nullable(); // taken off the traveller's post on agreement
            $table->timestamp('introduced_at')->nullable();
            $table->timestamp('agreed_owner_at')->nullable();
            $table->timestamp('agreed_requester_at')->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->date('checkin_on')->nullable();
            $table->timestamp('checkin_sent_at')->nullable();
            $table->string('checkin_owner', 4)->nullable();      // yes | no | wait
            $table->string('checkin_requester', 4)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('failed_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('moderator_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'requester_id']);
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('goods_matches')->cascadeOnDelete();
            $table->foreignId('from_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('to_member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            // Shown once both sides rated, or after seven days, so nobody rates in reply.
            $table->timestamp('visible_at');
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['match_id', 'from_member_id']);
            $table->index(['visible_at', 'counted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('goods_matches');
        Schema::dropIfExists('goods_posts');
    }
};
