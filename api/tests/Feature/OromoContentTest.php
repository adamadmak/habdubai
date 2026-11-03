<?php

use Anthropic\Client;
use App\Ai\ClaudeJson;
use App\Models\AnnouncementTopic;
use App\Models\DirectoryCity;
use App\Support\JobSettings;
use Laravel\Sanctum\Sanctum;

// Afaan Oromoo, the fourth language: what Claude writes reaches the website, and readers fall back to English.

test('an announcement is translated into Afaan Oromoo too, and the website gets it', function (): void {
    Sanctum::actingAs(admin(), ['admin']);
    JobSettings::setAnthropicKey('sk-ant-api03-test-key-not-real-0000');
    $transport = fakeTransport([[200, claudeResponse([
        'am' => 'ቆንስላው ሰኞ ዝግ ነው።', 'ti' => 'ቆንስላ ሰኑይ ዕጹው እዩ።', 'en' => 'The consulate is closed on Monday.', 'om' => 'Qonsilaan Wiixata cufaadha.',
    ])]]);
    app()->instance(ClaudeJson::class, new ClaudeJson(new Client(apiKey: 'sk-ant-test', requestOptions: ['transporter' => $transport, 'maxRetries' => 0])));

    $topic = AnnouncementTopic::query()->where('key', 'consulate')->firstOrFail();
    $id = $this->postJson('/api/admin/announcements', ['topic_id' => $topic->id, 'text' => 'The consulate is closed on Monday.'])
        ->assertCreated()->assertJsonPath('announcement.text_om', 'Qonsilaan Wiixata cufaadha.')->json('announcement.id');

    $this->getJson('/api/public/announcements/'.$id)->assertOk()
        ->assertJsonPath('announcement.text.om', 'Qonsilaan Wiixata cufaadha.')
        ->assertJsonPath('announcement.topic.name.om', 'Qonsilaa fi embaasii');
    $this->getJson('/api/public/announcements?q=Wiixata')->assertOk()->assertJsonPath('meta.total', 1);
});

test('a job summary in Afaan Oromoo reaches the website; an empty one falls back to English', function (): void {
    fakeClassifier(['summary_om' => 'Hojii konkolaachisaa Dubaay keessatti.']);
    $job = ingest();
    $this->getJson('/api/public/jobs/'.$job->id)->assertOk()->assertJsonPath('job.summary.om', 'Hojii konkolaachisaa Dubaay keessatti.');

    fakeClassifier();
    $other = ingest(['title' => 'Warehouse helper']);
    $this->getJson('/api/public/jobs/'.$other->id)->assertOk()->assertJsonPath('job.summary.om', 'A driver job in Dubai.');
});

test('cities carry an Oromo name for the website, and owners can read API messages in Afaan Oromoo', function (): void {
    expect(DirectoryCity::query()->where('key', 'dubai')->value('name_om'))->toBe('Dubai');
    $this->getJson('/api/public/directory')->assertOk()->assertJsonPath('cities.0.name.om', fn ($name) => $name !== null && $name !== '');

    // The locale middleware accepts om; a message missing in Oromo falls back to English, never to a key.
    \App\Models\PlatformSetting::put('telegram_bot_username', 'HabeshaDubaiBot');
    $start = $this->withHeader('X-Locale', 'om')->postJson('/api/owner/login/start')->assertOk();
    expect($start->json('ref'))->not->toBeEmpty();
});
