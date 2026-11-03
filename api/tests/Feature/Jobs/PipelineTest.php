<?php

use App\Jobs\Pipeline\ClassifierDeclined;
use App\Jobs\Pipeline\ClassifierUnavailable;
use App\Jobs\ProcessJobListing;
use App\Models\Job;
use App\Models\JobRaw;
use App\Models\PlatformSetting;
use App\Support\JobSettings;

test('a clean listing is published to the channel with Open, Save and Report buttons', function (): void {
    $channel = jobsChannel();
    fakeClassifier();

    $job = ingest(['title' => 'Light Vehicle Driver', 'company' => 'Fast Logistics']);

    expect($job->status)->toBe('published')
        ->and($job->channel_message_id)->toBe(1)
        ->and($job->summary_am)->toBe('ሹፌር በዱባይ');

    $post = collect(telegramCalls('sendMessage'))->firstWhere('chat_id', $channel);
    $buttons = collect($post['reply_markup']['inline_keyboard'])->flatten(1);

    expect($post['text'])->toContain('Light Vehicle Driver')->toContain('Fast Logistics')->toContain('AED 2,500 – 3,000')
        ->toContain('ሹፌር በዱባይ')->toContain('ሹፌር ኣብ ዱባይ')->toContain('#driver #dubai')
        ->and($buttons->pluck('url')->filter()->first())->toStartWith('https://jobs.example.com/view/')
        ->and($buttons->pluck('callback_data')->filter()->values()->all())->toBe(['job:save:'.$job->id, 'job:report:'.$job->id]);
});

test('without a channel a job is still published, just not posted', function (): void {
    fakeClassifier();

    $job = ingest();

    expect($job->status)->toBe('published')->and($job->channel_message_id)->toBeNull()
        ->and(telegramCalls('sendMessage'))->toBe([]);
});

test('a high scam score holds the job for review and nothing is posted', function (): void {
    jobsChannel();
    fakeClassifier(['scam_risk' => 80, 'scam_signals' => ['Asks for a visa fee']]);

    $job = ingest();

    expect($job->status)->toBe('review')
        ->and($job->scam_score)->toBe(80)
        ->and($job->review_reason)->toContain('Scam score 80')
        ->and($job->scam_reasons)->toContain('Model: Asks for a visa fee')
        ->and(telegramCalls('sendMessage'))->toBe([]);
});

test('our own rules hold a fee-charging job even when the model sees nothing', function (): void {
    fakeClassifier(['scam_risk' => 0]);

    $job = ingest(['text' => 'Hiring drivers in Dubai! Pay the registration fee of AED 500 to secure your visa. Contact on WhatsApp only. No interview needed, guaranteed visa for everyone.']);

    expect($job->status)->toBe('review')
        ->and($job->scam_score)->toBeGreaterThanOrEqual(50)
        ->and($job->scam_reasons)->toContain('Asks the applicant for a fee or deposit')
        ->and($job->scam_reasons)->toContain('Promises a job or visa without an interview');
});

test('a moderator phrase and a blocked company always hold', function (): void {
    fakeClassifier();
    PlatformSetting::put('job_scam_phrases', "golden ticket\n");
    JobSettings::blockCompany('Shady Recruiters LLC');

    $phrase = ingest(['text' => 'Your golden ticket to Dubai. Drivers wanted for a long-term contract with good pay and housing.']);
    $company = ingest(['company' => 'Shady Recruiters']);

    expect($phrase->status)->toBe('review')->and($phrase->scam_reasons)->toContain('Contains a phrase moderators marked: "golden ticket"')
        ->and($company->status)->toBe('review')->and($company->scam_score)->toBeGreaterThanOrEqual(60);
});

test('a medium score is published with a warning line on the card', function (): void {
    $channel = jobsChannel();
    fakeClassifier(['scam_risk' => 35, 'scam_signals' => ['Vague employer details']]);

    ingest();

    $post = collect(telegramCalls('sendMessage'))->firstWhere('chat_id', $channel);
    expect($post['text'])->toContain('⚠️')->toContain('vague employer details');
});

test('a post that is not a job is rejected without a card', function (): void {
    jobsChannel();
    fakeClassifier(['is_job' => false]);

    $job = ingest();

    expect($job->status)->toBe('rejected')->and($job->review_reason)->toContain('Not a job')
        ->and(telegramCalls('sendMessage'))->toBe([]);
});

test('jobs outside the UAE and everything while auto-publish is off go to review', function (): void {
    fakeClassifier(['emirate' => 'outside_uae']);
    expect(ingest()->status)->toBe('review');

    fakeClassifier();
    PlatformSetting::put('job_auto_publish', '0');
    expect(ingest()->status)->toBe('review')->and(Job::query()->latest('id')->first()->review_reason)->toBe('Auto-publish is off.');
});

test('the same URL from another source is a duplicate and the classifier is not called', function (): void {
    fakeClassifier();
    $original = ingest(['url' => 'https://jobs.example.com/view/777', 'title' => 'Waiter']);

    $classifier = app(\App\Jobs\Pipeline\JobClassifier::class);
    $callsBefore = $classifier->calls;

    $copy = ingest(['url' => 'https://www.jobs.example.com/view/777/?utm_source=telegram', 'title' => 'Waiter at hotel'], jobSource());

    expect($copy->status)->toBe('duplicate')
        ->and($copy->duplicate_of)->toBe($original->id)
        ->and($classifier->calls)->toBe($callsBefore);
});

test('the same title and company within a week is a duplicate', function (): void {
    fakeClassifier();
    $original = ingest(['url' => null, 'title' => 'Security Guard', 'company' => 'Safe Co', 'text' => 'First wording of the security guard listing at a mall in Dubai with night shifts.']);
    $copy = ingest(['url' => null, 'title' => 'Security guard!', 'company' => 'SAFE CO', 'text' => 'Different wording entirely about a guard role here.']);

    expect($copy->status)->toBe('duplicate')->and($copy->duplicate_of)->toBe($original->id);
});

test('nearly the same text is a duplicate even with a different title', function (): void {
    fakeClassifier();
    $text = 'We are hiring experienced housemaids for families in Jumeirah and Al Barsha. Salary 1800 to 2200 dirhams, visa, ticket and medical insurance provided by the sponsor. Must speak basic English and have two years of experience.';
    $original = ingest(['url' => null, 'title' => 'Housemaid', 'company' => 'A', 'text' => $text]);
    $copy = ingest(['url' => null, 'title' => 'Maids needed', 'company' => 'B', 'text' => $text.' Apply today.']);

    expect($copy->status)->toBe('duplicate')->and($copy->duplicate_of)->toBe($original->id);
});

test('a classifier that declines sends the listing to review, classified by rules', function (): void {
    jobsChannel();
    fakeClassifier(throw: new ClassifierDeclined('The model declined this listing (cyber).'));

    $job = ingest();

    expect($job->status)->toBe('review')
        ->and($job->classified_by)->toBe('rules')
        ->and($job->review_reason)->toContain('declined');
});

test('an unavailable classifier is retried, and on the last attempt the listing is held, never lost', function (): void {
    fakeClassifier(throw: new ClassifierUnavailable('overloaded'));
    $source = jobSource();
    $raw = JobRaw::query()->create(['source_id' => $source->id, 'external_key' => 'x1', 'title' => 'Cook', 'raw_text' => 'Cook wanted for a restaurant in Karama, Dubai. Indian and Arabic cuisine.', 'fetched_at' => now()]);

    // Not the last attempt: the job throws so the queue retries it.
    expect(fn () => app()->call([new ProcessJobListing($raw->id), 'handle']))->toThrow(ClassifierUnavailable::class);
    expect(Job::query()->count())->toBe(0);

    $last = new class($raw->id) extends ProcessJobListing
    {
        public function attempts(): int
        {
            return 3;
        }
    };
    app()->call([$last, 'handle']);

    $job = Job::query()->first();
    expect($job->status)->toBe('review')->and($job->review_reason)->toContain('overloaded')
        ->and($job->category)->toBe('hospitality');
});
