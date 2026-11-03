<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The public website: business owners who sign in with Telegram, their
 * listings with photos and pending edits, ownership claims on listings the
 * community added, and click counts on jobs that send people to the source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_owners', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_user_id')->unique();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name', 120);
            $table->string('telegram_username', 64)->nullable();
            $table->string('language', 2)->default('en');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamps();
        });

        // "Continue with Telegram": the website holds the secret, the bot link carries the ref.
        Schema::create('owner_login_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 32)->unique();
            $table->string('secret_hash', 64);
            $table->string('check_code', 4);
            // pending → approved → used | declined | expired
            $table->string('status', 10)->default('pending');
            $table->bigInteger('telegram_user_id')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('business_owners')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 200)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('submitted_by')->constrained('business_owners')->nullOnDelete();
            $table->string('slug', 160)->nullable()->unique();
            $table->text('about')->nullable();
            $table->string('website', 200)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('hours', 160)->nullable();
            $table->json('languages')->nullable();
            // An owner's edits to a live listing wait here until a moderator approves them.
            $table->json('pending_changes')->nullable();
            $table->timestamp('changes_submitted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->bigInteger('channel_message_id')->nullable();
            $table->unsignedInteger('views')->default(0);
        });

        // Listings from before the website get their address now.
        foreach (\Illuminate\Support\Facades\DB::table('businesses')->whereNull('slug')->get(['id', 'name']) as $row) {
            \Illuminate\Support\Facades\DB::table('businesses')->where('id', $row->id)
                ->update(['slug' => \App\Models\Business::slugFor((string) $row->name, (int) $row->id)]);
        }

        // In the database, not on disk: the container is replaced on every deploy.
        // Base64 text rather than a binary column: Postgres refuses raw bytes bound as text.
        Schema::create('business_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('cover');
            $table->boolean('approved')->default(false);
            $table->string('mime', 16);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('size');
            $table->longText('data');
            $table->timestamps();

            $table->index(['business_id', 'kind', 'approved']);
        });

        Schema::create('business_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('business_owners')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->string('status', 10)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 250)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'owner_id']);
        });

        Schema::table('jobs_listings', function (Blueprint $table) {
            $table->unsignedInteger('clicks')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('jobs_listings', fn (Blueprint $table) => $table->dropColumn('clicks'));
        Schema::dropIfExists('business_claims');
        Schema::dropIfExists('business_photos');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'about', 'website', 'whatsapp', 'hours', 'languages', 'pending_changes', 'changes_submitted_at', 'submitted_at', 'channel_message_id', 'views']);
        });
        Schema::dropIfExists('owner_login_requests');
        Schema::dropIfExists('business_owners');
    }
};
