<?php

use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;

const OWN_CHANNEL = -1003960063900;

beforeEach(function (): void {
    PlatformSetting::put('job_channel_id', '@habuae');
    TelegramFake::$results['getFile'] = ['file_id' => 'x', 'file_path' => 'photos/flyer.jpg'];
    TelegramFake::$files['flyer.jpg'] = jpegBytes(720, 1280);
});

/** A post an admin makes by hand on our channel, as Telegram delivers it. */
function channelPost(array $post, int $chatId = OWN_CHANNEL, string $username = 'habuae'): void
{
    test()->postJson('/api/telegram/webhook/'.webhookSecret(), [
        'update_id' => nextUpdateId(),
        'channel_post' => ['message_id' => nextUpdateId(), 'date' => time(), 'chat' => ['id' => $chatId, 'type' => 'channel', 'username' => $username]] + $post,
    ])->assertOk();
}

function flyer(string $id = 'flyer-1'): array
{
    return ['photo' => [['file_id' => $id.'-small', 'width' => 90, 'height' => 160], ['file_id' => $id, 'width' => 720, 'height' => 1280]]];
}

test('a flyer with no words goes up by itself, with its picture, and gets no second post on the channel', function (): void {
    channelPost(flyer());

    $job = Job::query()->sole();

    expect($job->status)->toBe('published')
        ->and($job->image_only)->toBeTrue()
        ->and($job->website_only)->toBeTrue()
        ->and($job->channel_message_id)->toBeNull()
        ->and($job->url)->toStartWith('https://t.me/habuae/')
        ->and($job->source->key)->toBe('own-channel')
        ->and(JobPhoto::query()->where('job_id', $job->id)->count())->toBe(1)
        ->and(telegramCalls('sendMessage'))->toBe([])
        ->and(collect(telegramCalls('getFile'))->last()['file_id'])->toBe('flyer-1');

    $shown = $this->getJson('/api/public/jobs/'.$job->id)->assertOk()->json('job');
    expect($shown['image_only'])->toBeTrue()
        ->and($shown['photo'])->toContain('w=480')
        ->and($shown['photos'])->toHaveCount(1)
        ->and($shown['source'])->toBe('HabUAE channel')
        ->and($this->getJson('/api/public/jobs')->json('data.0.photo'))->toContain('/job-photos/');

    $bytes = $this->get(parse_url($shown['photos'][0]['url'], PHP_URL_PATH))->assertOk();
    expect($bytes->headers->get('Content-Type'))->toBe('image/jpeg');
});

test('an album with no words is one job per flyer, because each is a different employer', function (): void {
    channelPost(flyer('a') + ['media_group_id' => 'g1']);
    channelPost(flyer('b') + ['media_group_id' => 'g1']);
    channelPost(flyer('c') + ['media_group_id' => 'g1']);

    expect(Job::query()->where('status', 'published')->count())->toBe(3)
        ->and(JobPhoto::query()->count())->toBe(3);
});

test('an album with words on it is one job with all its pictures', function (): void {
    channelPost(flyer('a') + ['media_group_id' => 'g2', 'caption' => "Chef needed in Abu Dhabi\nHotel kitchen, AED 3500, call 0501234567."]);
    channelPost(flyer('b') + ['media_group_id' => 'g2']);

    $job = Job::query()->sole();

    expect($job->status)->toBe('published')
        ->and($job->image_only)->toBeFalse()
        ->and($job->title)->toBe('Chef needed in Abu Dhabi')
        ->and($job->emirate)->toBe('abu_dhabi')
        ->and(JobPhoto::query()->where('job_id', $job->id)->count())->toBe(2);
});

test('a post that is only a link becomes a job from the page preview, without who shared it', function (): void {
    Http::fake(['www.linkedin.com/*' => Http::response('<html><head><meta property="og:title" content="#hiring | EDEN Clinic"><meta property="og:description" content="We&#39;re Hiring! | Receptionist – Dubai
Front desk at a clinic, full time."><meta property="og:image" content="https://media.licdn.com/flyer.jpg"></head></html>'), 'media.licdn.com/*' => Http::response(jpegBytes(800, 800))]);

    channelPost(['text' => 'https://www.linkedin.com/posts/eden-clinic-7524?utm_source=share&utm_medium=member_android&rcm=ACoAAAt7Bps']);

    $job = Job::query()->sole();

    expect($job->status)->toBe('published')
        ->and($job->url)->toBe('https://www.linkedin.com/posts/eden-clinic-7524')
        ->and($job->title)->toBe('Receptionist – Dubai')
        ->and($job->emirate)->toBe('dubai')
        ->and($job->image_only)->toBeFalse()
        ->and(JobPhoto::query()->where('job_id', $job->id)->count())->toBe(1)
        ->and($this->getJson('/api/public/jobs/'.$job->id)->json('job.source'))->toBe('linkedin.com');
});

test('a link with nothing readable and no picture waits for a moderator instead of showing an empty job', function (): void {
    Http::fake(['careers.example.com/*' => Http::response('', 403)]);

    channelPost(['text' => 'https://careers.example.com/driver-job-1234']);

    $job = Job::query()->sole();

    expect($job->status)->toBe('review')->and($job->review_reason)->toContain('Nothing could be read');
});

test('ordinary channel talk and an opted-out photo do not become jobs', function (): void {
    channelPost(['text' => 'Welcome everyone, and thank you for joining our community channel today.']);
    channelPost(['text' => 'short']);
    channelPost(flyer() + ['caption' => 'Our new logo #nojob']);

    Http::fake(['example.com/*' => Http::response('<meta property="og:title" content="A nice article about the weather">')]);
    channelPost(['text' => 'https://example.com/weather']);

    expect(Job::query()->count())->toBe(0);
});

test('a text post about work publishes, a fee request carries a warning, and a clear scam waits for a moderator', function (): void {
    channelPost(['text' => "Cleaner required in Sharjah\nAED 1800 with accommodation, call 0507654321 to apply."]);
    channelPost(['text' => "Driver wanted in Dubai\nPay the visa fee of AED 500 first, then start work next week."]);
    channelPost(['text' => "Waiter wanted in Dubai\nPay the visa fee of AED 500, guaranteed job, no interview at all."]);

    $warned = Job::query()->where('title', 'Driver wanted in Dubai')->sole();

    expect(Job::query()->where('title', 'Cleaner required in Sharjah')->value('status'))->toBe('published')
        ->and($warned->status)->toBe('published')
        ->and($warned->scam_score)->toBeGreaterThanOrEqual(30)
        ->and(Job::query()->where('title', 'Waiter wanted in Dubai')->value('status'))->toBe('review');
});

test('the channel is recognised by its number too, and a post from any other channel stays ignored', function (): void {
    channelPost(flyer(), -100999, 'someone_else');
    expect(Job::query()->count())->toBe(0);

    PlatformSetting::put('job_channel_id', (string) OWN_CHANNEL);
    channelPost(flyer(), OWN_CHANNEL, 'renamed');
    expect(Job::query()->count())->toBe(1);
});

test('taking a channel job down or closing it never touches the post on the channel', function (): void {
    channelPost(flyer());
    $job = Job::query()->sole();

    app(App\Jobs\Pipeline\JobPublisher::class)->close($job);
    app(App\Jobs\Pipeline\JobPublisher::class)->hold($job->fresh(), 'A person looked at it.');

    expect(telegramCalls('deleteMessage'))->toBe([])->and(telegramCalls('editMessageText'))->toBe([]);
});

test('a picture of a job nobody may see yet is not served', function (): void {
    channelPost(flyer());
    $job = Job::query()->sole();
    $job->forceFill(['status' => 'review'])->save();

    $this->get('/api/public/job-photos/'.JobPhoto::query()->value('id'))->assertNotFound();
});

test('a moderator forwarding one of our own flyers adds it for the website and posts nothing', function (): void {
    joinMember(80, '+971501110001')->forceFill(['role' => 'moderator'])->save();
    joinMember(81, '+971501110002');

    $forward = fn (int $userId) => test()->postJson('/api/telegram/webhook/'.webhookSecret(), telegramMessage($userId, flyer() + [
        'forward_origin' => ['type' => 'channel', 'chat' => ['id' => OWN_CHANNEL, 'type' => 'channel', 'username' => 'habuae'], 'message_id' => 15, 'date' => time()],
    ]));

    $forward(81);
    expect(Job::query()->count())->toBe(0);

    $forward(80);
    $job = Job::query()->sole();

    expect($job->status)->toBe('published')
        ->and($job->url)->toBe('https://t.me/habuae/15')
        ->and($job->channel_message_id)->toBeNull()
        ->and(JobPhoto::query()->where('job_id', $job->id)->count())->toBe(1);

    $forward(80);
    expect(Job::query()->count())->toBe(1);
});

test('a phone screenshot keeps the flyer and loses the status bar and black bands; a plain picture is untouched', function (): void {
    $gd = imagecreatetruecolor(400, 800);
    imagefill($gd, 0, 0, imagecolorallocate($gd, 0, 0, 0));
    imagefilledrectangle($gd, 0, 0, 399, 39, imagecolorallocate($gd, 90, 90, 90));
    imagefilledrectangle($gd, 0, 200, 399, 599, imagecolorallocate($gd, 30, 80, 200));
    imagefilledrectangle($gd, 0, 740, 399, 799, imagecolorallocate($gd, 90, 90, 90));
    ob_start();
    imagejpeg($gd, null, 90);
    $screenshot = (string) ob_get_clean();

    $trimmed = App\Jobs\Pipeline\JobPhotos::withoutPhoneBars(['jpeg' => $screenshot, 'width' => 400, 'height' => 800]);
    expect($trimmed['height'])->toBeGreaterThan(390)->toBeLessThan(410)->and(imagesy(imagecreatefromstring($trimmed['jpeg'])))->toBe($trimmed['height']);

    $plain = ['jpeg' => jpegBytes(400, 800), 'width' => 400, 'height' => 800];
    expect(App\Jobs\Pipeline\JobPhotos::withoutPhoneBars($plain))->toBe($plain);
});
