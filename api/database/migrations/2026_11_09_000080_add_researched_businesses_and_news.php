<?php

use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\Business;
use App\Models\DirectoryCity;
use Illuminate\Database\Migrations\Migration;

/**
 * The directory and news page's first real content, asked for by the owner on 5 Nov 2026
 * and researched from public sources (database/data). Migrations are how this host takes a
 * change, since it has no shell.
 *
 * Written directly, not through the publishers: forty channel posts and a message to
 * every member at once is not what was asked for. Nothing here overwrites a row: a name
 * or text already present is left alone. Not in the test suite's empty database.
 */
return new class extends Migration
{
    private const UNCONFIRMED = 'Added by HabUAE from public listings. The business has not confirmed these details, so please call before you go. Is this your business? Claim this listing to keep it right.';

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $cities = DirectoryCity::query()->pluck('id', 'key');

        foreach (require database_path('data/businesses_2026_11.php') as $b) {
            $city = $cities[$b['city']] ?? null;

            if ($city === null || Business::query()->where('city_id', $city)->whereRaw('lower(name) = ?', [mb_strtolower($b['name'])])->exists()) {
                continue;
            }

            Business::query()->create([
                'name' => $b['name'],
                'category' => $b['category'],
                'city_id' => $city,
                'area' => $b['area'],
                'phone' => $b['phone'],
                'whatsapp' => $b['whatsapp'],
                'website' => $b['website'],
                'description' => $b['description'],
                'about' => self::UNCONFIRMED,
                'status' => 'approved',
                'verified' => false,
                'reviewed_at' => now(),
            ]);
        }

        $topics = AnnouncementTopic::query()->pluck('id', 'key');

        foreach (require database_path('data/news_2026_11.php') as $n) {
            if (! isset($topics[$n['topic']]) || Announcement::query()->where('text_en', $n['en'])->exists()) {
                continue;
            }

            Announcement::query()->create([
                'topic_id' => $topics[$n['topic']],
                'original_language' => 'en',
                'text_en' => $n['en'],
                'text_am' => $n['am'],
                'text_ti' => $n['ti'],
                'text_om' => $n['om'],
                'status' => 'published',
                'published_at' => $n['published_at'],
            ]);
        }
    }

    /** Only what this added and nobody has since taken over or changed. */
    public function down(): void
    {
        $names = array_column(require database_path('data/businesses_2026_11.php'), 'name');
        Business::query()->whereIn('name', $names)->whereNull('owner_id')->where('about', self::UNCONFIRMED)->delete();
        Announcement::query()->whereIn('text_en', array_column(require database_path('data/news_2026_11.php'), 'en'))->delete();
    }
};
