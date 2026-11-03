<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where listings come from. `kind` picks the fetcher (rss, html,
        // telegram); `config` holds what that fetcher needs (feed URL, CSS
        // selectors, channel id). Admin-managed on the Sources page.
        Schema::create('job_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 120);
            $table->string('kind', 16);
            $table->json('config');
            $table->unsignedInteger('interval_minutes')->default(60);
            $table->boolean('active')->default(true);
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('failures_in_row')->default(0);
            $table->unsignedInteger('last_found')->default(0);
            $table->unsignedInteger('last_new')->default(0);
            $table->timestamps();
        });

        // Every listing a source returned, as fetched. The unique key makes a
        // re-fetch of the same listing a no-op. Kept thirty days.
        Schema::create('job_raw', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('job_sources')->cascadeOnDelete();
            $table->string('external_key', 191);
            $table->string('url', 1000)->nullable();
            $table->string('title', 300)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('location', 200)->nullable();
            $table->text('raw_text');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('processed_at')->nullable();

            $table->unique(['source_id', 'external_key']);
            $table->index('fetched_at');
        });

        Schema::create('jobs_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_id')->nullable()->constrained('job_raw')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('job_sources')->nullOnDelete();
            $table->foreignId('duplicate_of')->nullable()->constrained('jobs_listings')->nullOnDelete();
            $table->string('url', 1000)->nullable();
            $table->string('url_key', 64)->nullable()->index();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->string('category', 24)->default('other');
            $table->string('emirate', 16)->default('unknown');
            $table->string('sub_area', 80)->nullable();
            $table->string('title', 300);
            $table->string('company', 200)->nullable();
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('salary_currency', 8)->nullable();
            $table->string('accommodation', 8)->default('unknown');
            $table->string('visa', 8)->default('unknown');
            $table->json('languages')->nullable();
            $table->text('summary_am')->nullable();
            $table->text('summary_ti')->nullable();
            $table->text('summary_en')->nullable();
            $table->unsignedTinyInteger('scam_score')->default(0);
            $table->json('scam_reasons')->nullable();
            $table->string('classified_by', 16)->default('rules');
            // pending → published | review → published | rejected; duplicate; closed
            $table->string('status', 16)->default('pending')->index();
            $table->string('review_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('channel_message_id')->nullable();
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamps();
        });

        Schema::create('job_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('categories')->nullable();
            $table->json('emirates')->nullable();
            $table->unsignedInteger('salary_min')->nullable();
            $table->string('keyword', 60)->nullable();
            $table->boolean('alerts_on')->default(true);
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamps();
        });

        // One row per job a member should hear about; sent_at null = queued.
        Schema::create('job_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs_listings')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->timestamp('sent_at')->nullable();

            $table->unique(['member_id', 'job_id']);
            $table->index(['sent_at', 'member_id']);
        });

        Schema::create('job_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs_listings')->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->unique(['member_id', 'job_id']);
        });

        Schema::create('job_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs_listings')->cascadeOnDelete();
            $table->string('reason', 24)->default('fake');
            $table->timestamp('created_at');

            $table->unique(['member_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reports');
        Schema::dropIfExists('job_saves');
        Schema::dropIfExists('job_alerts');
        Schema::dropIfExists('job_filters');
        Schema::dropIfExists('jobs_listings');
        Schema::dropIfExists('job_raw');
        Schema::dropIfExists('job_sources');
    }
};
