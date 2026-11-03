<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the bot, the website and the admin filter and sort
 * on. Postgres, unlike MySQL, does not index a foreign key by itself, so a
 * lookup by member_id or post_id read the whole table.
 *
 * Every index is added only when its table and columns exist and no index
 * already covers it (by name or by the same columns), so this is safe on the
 * live database whatever state it is in, and safe to run twice.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: array<int, string>, 2: string}> table, columns, index name (unique across the schema) */
    private const INDEXES = [
        ['members', ['verified_at'], 'members_verified_at_idx'],
        ['telegram_updates', ['received_at'], 'telegram_updates_received_at_idx'],
        ['audit_log', ['created_at'], 'audit_log_created_at_idx'],
        ['jobs_listings', ['status', 'published_at'], 'jobs_listings_status_published_idx'],
        ['jobs_listings', ['source_id'], 'jobs_listings_source_idx'],
        ['jobs_listings', ['raw_id'], 'jobs_listings_raw_idx'],
        ['jobs_listings', ['created_at'], 'jobs_listings_created_idx'],
        ['job_reports', ['job_id'], 'job_reports_job_idx'],
        ['businesses', ['submitted_by', 'status'], 'businesses_submitter_status_idx'],
        ['businesses', ['owner_id'], 'businesses_owner_idx'],
        ['business_reports', ['business_id'], 'business_reports_business_idx'],
        ['business_claims', ['owner_id'], 'business_claims_owner_idx'],
        ['questions', ['member_id', 'created_at'], 'questions_member_created_idx'],
        ['questions', ['group_chat_id', 'group_message_id'], 'questions_group_message_idx'],
        ['assistant_messages', ['member_id', 'id'], 'assistant_messages_member_idx'],
        ['member_topic_prefs', ['topic_id', 'subscribed'], 'member_topic_prefs_topic_idx'],
        ['announcements', ['status', 'published_at'], 'announcements_status_published_idx'],
        ['announcements', ['topic_id', 'status'], 'announcements_topic_status_idx'],
        ['member_strikes', ['member_id', 'kind', 'created_at'], 'member_strikes_member_kind_idx'],
        ['goods_posts', ['member_id', 'status'], 'goods_posts_member_status_idx'],
        ['goods_matches', ['requester_id', 'created_at'], 'goods_matches_requester_created_idx'],
        ['goods_matches', ['owner_id', 'status'], 'goods_matches_owner_status_idx'],
        ['goods_matches', ['requester_post_id'], 'goods_matches_requester_post_idx'],
        ['goods_matches', ['failed_member_id', 'status'], 'goods_matches_failed_member_idx'],
        ['ratings', ['to_member_id', 'visible_at'], 'ratings_to_member_idx'],
        ['owner_login_requests', ['expires_at'], 'owner_login_requests_expires_idx'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $columns, $name]) {
            if (! $this->applies($table, $columns) || Schema::hasIndex($table, $name) || Schema::hasIndex($table, $columns)) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, $columns, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }

    /** @param array<int, string> $columns */
    private function applies(string $table, array $columns): bool
    {
        return Schema::hasTable($table) && Schema::hasColumns($table, $columns);
    }
};
