<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_topics', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name_en', 80);
            $table->string('name_am', 80);
            $table->string('name_ti', 80);
            $table->string('emoji', 8)->default('📣');
            // Deaths and fundraisers are what scammers copy: a moderator approves them.
            $table->boolean('needs_approval')->default(false);
            $table->boolean('default_on')->default(false);
            $table->boolean('pin')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // A member's explicit choice; with no row, the topic's default applies.
        Schema::create('member_topic_prefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('announcement_topics')->cascadeOnDelete();
            $table->boolean('subscribed');
            $table->unique(['member_id', 'topic_id']);
        });

        Schema::create('leader_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('announcement_topics')->cascadeOnDelete();
            $table->unique(['member_id', 'topic_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('announcement_topics')->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_language', 2);
            $table->text('text_am');
            $table->text('text_ti');
            $table->text('text_en');
            $table->string('photo_file_id', 200)->nullable();
            $table->date('event_date')->nullable();
            $table->string('event_place', 160)->nullable();
            // pending → published | rejected; published → deleted
            $table->string('status', 10)->default('pending')->index();
            $table->string('review_note', 250)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('channel_message_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('pinned_until')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->unsignedInteger('dm_count')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('announcement_topics')->insert(array_map(fn (array $t) => $t + ['active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['key' => 'consulate', 'emoji' => '🇪🇹', 'name_en' => 'Consulate and embassy', 'name_am' => 'ቆንስላና ኤምባሲ', 'name_ti' => 'ቆንስላን ኤምባሲን', 'needs_approval' => false, 'default_on' => true, 'pin' => false, 'sort' => 10],
            ['key' => 'safety', 'emoji' => '🚨', 'name_en' => 'Safety alerts', 'name_am' => 'የደህንነት ማስጠንቀቂያ', 'name_ti' => 'መጠንቀቕታ ድሕነት', 'needs_approval' => false, 'default_on' => true, 'pin' => true, 'sort' => 20],
            ['key' => 'platform', 'emoji' => '📢', 'name_en' => 'Community news', 'name_am' => 'የማህበረሰቡ ዜና', 'name_ti' => 'ዜና ማሕበረሰብ', 'needs_approval' => false, 'default_on' => true, 'pin' => false, 'sort' => 30],
            ['key' => 'church', 'emoji' => '⛪', 'name_en' => 'Church', 'name_am' => 'ቤተ ክርስቲያን', 'name_ti' => 'ቤተ ክርስትያን', 'needs_approval' => false, 'default_on' => false, 'pin' => false, 'sort' => 40],
            ['key' => 'mosque', 'emoji' => '🕌', 'name_en' => 'Mosque', 'name_am' => 'መስጊድ', 'name_ti' => 'መስጊድ', 'needs_approval' => false, 'default_on' => false, 'pin' => false, 'sort' => 50],
            ['key' => 'events', 'emoji' => '🎉', 'name_en' => 'Community events', 'name_am' => 'የማህበረሰብ ዝግጅቶች', 'name_ti' => 'ምድላዋት ማሕበረሰብ', 'needs_approval' => false, 'default_on' => false, 'pin' => false, 'sort' => 60],
            ['key' => 'travel', 'emoji' => '✈️', 'name_en' => 'Flights and travel', 'name_am' => 'በረራና ጉዞ', 'name_ti' => 'በረራን ጉዕዞን', 'needs_approval' => false, 'default_on' => false, 'pin' => false, 'sort' => 70],
            ['key' => 'condolences', 'emoji' => '🕯', 'name_en' => 'Deaths and condolences', 'name_am' => 'ሐዘን', 'name_ti' => 'ሓዘን', 'needs_approval' => true, 'default_on' => false, 'pin' => false, 'sort' => 80],
            ['key' => 'fundraisers', 'emoji' => '🤝', 'name_en' => 'Fundraisers', 'name_am' => 'የገንዘብ ማሰባሰቢያ', 'name_ti' => 'ምእካብ ገንዘብ', 'needs_approval' => true, 'default_on' => false, 'pin' => false, 'sort' => 90],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('leader_topics');
        Schema::dropIfExists('member_topic_prefs');
        Schema::dropIfExists('announcement_topics');
    }
};
