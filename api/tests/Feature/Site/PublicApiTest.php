<?php

use App\Models\Announcement;
use App\Models\AnnouncementTopic;
use App\Models\Business;
use App\Models\DirectoryCity;
use App\Models\Job;
use App\Models\PlatformSetting;

function publicJob(array $overrides = []): Job
{
    return Job::query()->create(array_merge([
        'url' => 'https://www.indeed.com/viewjob?jk=abc123',
        'title' => 'Light Vehicle Driver',
        'company' => 'Fast Logistics',
        'category' => 'driver',
        'emirate' => 'dubai',
        'sub_area' => 'Deira',
        'salary_min' => 2500,
        'salary_max' => 3000,
        'accommodation' => 'yes',
        'summary_en' => 'Deliveries around Deira.',
        'summary_am' => 'በዴይራ አካባቢ ማድረስ።',
        'status' => 'published',
        'published_at' => now(),
    ], $overrides));
}

function publicBusiness(array $overrides = []): Business
{
    return Business::query()->create(array_merge([
        'name' => 'Abyssinia Spices',
        'category' => 'grocery',
        'city_id' => DirectoryCity::query()->where('key', 'dubai')->value('id'),
        'area' => 'Deira, Naif',
        'phone' => '+971501112233',
        'description' => 'Berbere, shiro and coffee.',
        'status' => 'approved',
    ], $overrides));
}

function publicAnnouncement(array $overrides = []): Announcement
{
    return Announcement::query()->create(array_merge([
        'topic_id' => AnnouncementTopic::query()->where('key', 'church')->value('id'),
        'original_language' => 'en',
        'text_am' => 'የጥምቀት በዓል',
        'text_ti' => 'በዓል ጥምቀት',
        'text_en' => 'Timket celebration at St. Mary church.',
        'status' => 'published',
        'published_at' => now(),
    ], $overrides));
}

beforeEach(function (): void {
    PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
    PlatformSetting::put('telegram_channel_link', 'https://t.me/habesha_dubai');
});

test('the home page shows only what is public: published jobs, listed businesses, published news, upcoming events', function (): void {
    publicJob();
    publicJob(['title' => 'Held for review', 'status' => 'review']);
    publicBusiness();
    publicBusiness(['name' => 'Waiting Cafe', 'status' => 'pending']);
    publicBusiness(['name' => 'Hidden Salon', 'status' => 'hidden']);
    publicAnnouncement(['event_date' => now()->addDays(5)->toDateString(), 'event_place' => 'Oud Metha']);
    publicAnnouncement(['text_en' => 'Not yet approved', 'status' => 'pending']);
    joinMember(9100, '+971500009100');

    $home = $this->getJson('/api/public/home')->assertOk()->assertHeader('Cache-Control', 'max-age=60, public')->json();

    expect($home['stats'])->toBe(['members' => 1, 'jobs' => 1, 'businesses' => 1, 'goods' => 0])
        ->and(collect($home['jobs'])->pluck('title')->all())->toBe(['Light Vehicle Driver'])
        ->and(collect($home['businesses'])->pluck('name')->all())->toBe(['Abyssinia Spices'])
        ->and($home['events'][0]['event_place'])->toBe('Oud Metha')
        ->and($home['announcements'])->toHaveCount(1)
        ->and(collect($home['categories'])->firstWhere('key', 'grocery')['count'])->toBe(1)
        ->and($home['meta']['bot']['link'])->toBe('https://t.me/HabeshaDubaiBot')
        ->and($home['meta']['channel'])->toBe('https://t.me/habesha_dubai');
});

test('the home page survives a cache that serializes, as the database store on the host does', function (): void {
    config(['cache.default' => 'database']);
    publicBusiness();
    publicJob();

    $this->getJson('/api/public/home')->assertOk();
    $again = $this->getJson('/api/public/home')->assertOk()->json();

    expect(array_is_list($again['categories']))->toBeTrue()
        ->and($again['categories'][0]['key'])->toBe('restaurant')
        ->and($again['jobs'][0]['title'])->toBe('Light Vehicle Driver')
        ->and($again['businesses'][0]['name'])->toBe('Abyssinia Spices');
});

test('with the website address set, bot cards link to its pages', function (): void {
    PlatformSetting::put('site_url', 'https://habesha.example');
    $job = publicJob();
    $business = publicBusiness();

    $keyboard = app(\App\Jobs\Pipeline\JobCard::class)->channelKeyboard($job);
    expect(collect($keyboard['inline_keyboard'][0])->pluck('url')->all())
        ->toBe(['https://www.indeed.com/viewjob?jk=abc123', 'https://habesha.example/am/jobs/'.$job->id]);

    $member = joinMember(9200, '+971500009200');
    app(\App\Bot\DirectoryFlow::class)->card($member, $business->fresh());
    expect(collect(lastTo(9200)['reply_markup']['inline_keyboard'])->flatten(1)->pluck('url')->filter()->values()->all())
        ->toContain('https://habesha.example/en/directory/'.$business->slug);
});

test('jobs: filters, the detail with all three summaries, and Apply counts the click before going to the source', function (): void {
    $driver = publicJob();
    publicJob(['title' => 'Housemaid', 'category' => 'domestic', 'emirate' => 'sharjah', 'company' => 'Home Care']);
    $closed = publicJob(['title' => 'Old job', 'status' => 'closed']);

    $this->getJson('/api/public/jobs')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('counts.categories.driver', 1);
    $this->getJson('/api/public/jobs?category=domestic')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Housemaid');
    $this->getJson('/api/public/jobs?emirate=sharjah&q=home')->assertJsonCount(1, 'data');
    $this->getJson('/api/public/jobs?q=nothing-like-this')->assertJsonCount(0, 'data');

    $this->getJson('/api/public/jobs/'.$driver->id)->assertOk()
        ->assertJsonPath('job.salary', 'AED 2,500 – 3,000')->assertJsonPath('job.source', 'indeed.com')
        ->assertJsonPath('job.summary.am', 'በዴይራ አካባቢ ማድረስ።')->assertJsonPath('job.summary.ti', 'Deliveries around Deira.')
        ->assertJsonPath('job.apply_url', 'http://localhost/api/public/jobs/'.$driver->id.'/apply')
        ->assertJsonCount(0, 'similar');
    $this->getJson('/api/public/jobs/'.publicJob(['title' => 'Bus Driver'])->id)->assertJsonPath('similar.0.title', 'Light Vehicle Driver');

    $this->get('/api/public/jobs/'.$driver->id.'/apply')->assertRedirect('https://www.indeed.com/viewjob?jk=abc123');
    expect($driver->fresh()->clicks)->toBe(1);

    $this->get('/api/public/jobs/'.$closed->id.'/apply')->assertStatus(410);
    PlatformSetting::put('site_url', 'https://habesha.example');
    $this->get('/api/public/jobs/'.$closed->id.'/apply')->assertRedirect('https://habesha.example');

    $this->getJson('/api/public/jobs/'.publicJob(['status' => 'rejected'])->id)->assertNotFound();
});

test('directory: cities and kinds with counts, filters, the page by its address, and nothing unlisted', function (): void {
    $spices = publicBusiness(['verified' => true, 'languages' => ['am', 'ti']]);
    publicBusiness(['name' => 'Sharjah Coffee', 'category' => 'cafe', 'city_id' => DirectoryCity::query()->where('key', 'sharjah')->value('id')]);
    publicBusiness(['name' => 'Selam Grocery', 'area' => 'Al Karama']);
    $pending = publicBusiness(['name' => 'Waiting Cafe', 'status' => 'pending']);

    $facets = $this->getJson('/api/public/directory')->assertOk()->json();
    expect(collect($facets['cities'])->firstWhere('key', 'dubai')['count'])->toBe(2)
        ->and(collect($facets['categories'])->firstWhere('key', 'cafe')['count'])->toBe(1);

    $this->getJson('/api/public/businesses?city=dubai')->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'Abyssinia Spices');
    $this->getJson('/api/public/businesses?category=cafe')->assertJsonCount(1, 'data');
    $this->getJson('/api/public/businesses?q=karama')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Selam Grocery');

    $this->getJson('/api/public/businesses/'.$spices->slug)->assertOk()
        ->assertJsonPath('business.languages', ['am', 'ti'])->assertJsonPath('business.verified', true)
        ->assertJsonPath('nearby.0.name', 'Selam Grocery');
    expect($spices->fresh()->views)->toBe(1);

    $this->getJson('/api/public/businesses/'.$pending->slug)->assertNotFound();
});

test('announcements: news, upcoming events, one in all three languages, and its photo through the API', function (): void {
    $news = publicAnnouncement(['photo_file_id' => 'AgADphoto']);
    publicAnnouncement(['text_en' => 'Past event', 'event_date' => now()->subDays(3)->toDateString()]);
    publicAnnouncement(['text_en' => 'Coming soon', 'event_date' => now()->addDays(2)->toDateString()]);
    $pending = publicAnnouncement(['status' => 'pending']);

    $this->getJson('/api/public/announcements')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('topics.0.key', 'consulate');
    $this->getJson('/api/public/announcements?events=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.text.en', 'Coming soon');
    $this->getJson('/api/public/announcements?topic=mosque')->assertJsonCount(0, 'data');

    $this->getJson('/api/public/announcements/'.$news->id)->assertOk()
        ->assertJsonPath('announcement.text.am', 'የጥምቀት በዓል')->assertJsonPath('announcement.topic.key', 'church')
        ->assertJsonPath('announcement.photo', 'http://localhost/api/public/announcements/'.$news->id.'/photo');
    $this->getJson('/api/public/announcements/'.$pending->id)->assertNotFound();

    TelegramFake::$results['getFile'] = ['file_id' => 'AgADphoto', 'file_path' => 'photos/file_7.jpg'];
    TelegramFake::$files['file_7.jpg'] = jpegBytes(640, 480);
    $this->get('/api/public/announcements/'.$news->id.'/photo')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get('/api/public/announcements/'.$news->id.'/photo')->assertOk();
    // Fetched from Telegram once, then served from the cache; the token never appears in a URL we hand out.
    expect(telegramCalls('getFile'))->toHaveCount(1);
});
