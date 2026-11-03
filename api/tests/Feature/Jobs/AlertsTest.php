<?php

use App\Jobs\Pipeline\JobAlerts;
use App\Models\JobAlert;
use App\Models\JobFilter;

beforeEach(function (): void {
    fakeClassifier();
});

test('publishing queues an alert only for members whose filter matches', function (): void {
    $driver = joinMember(100, '+971502220001');
    $salon = joinMember(101, '+971502220002');
    JobFilter::forMember($salon)->forceFill(['categories' => ['salon']])->save();
    JobFilter::forMember($driver);

    $job = ingest();

    expect(JobAlert::query()->where('job_id', $job->id)->pluck('member_id')->all())->toBe([$driver->id]);
});

test('salary, emirate and keyword filters work, and a job without a stated salary still matches', function (): void {
    $member = joinMember(102);
    $filter = JobFilter::forMember($member);

    fakeClassifier(['salary_min' => 1500, 'salary_max' => 2000]);
    $low = ingest(['title' => 'Driver low pay']);
    fakeClassifier(['salary_min' => 0, 'salary_max' => 0]);
    $unstated = ingest(['title' => 'Driver unstated']);
    fakeClassifier(['emirate' => 'sharjah']);
    $sharjah = ingest(['title' => 'Driver Sharjah']);

    $filter->forceFill(['salary_min' => 2500, 'emirates' => ['dubai'], 'keyword' => 'driver'])->save();

    expect($filter->matches($low))->toBeFalse()
        ->and($filter->matches($unstated))->toBeTrue()
        ->and($filter->matches($sharjah))->toBeFalse();

    $filter->forceFill(['keyword' => 'forklift'])->save();
    expect($filter->matches($unstated))->toBeFalse();
});

test('alerts are batched into one message, at most every fifteen minutes', function (): void {
    $member = joinMember(103);
    JobFilter::forMember($member);
    foreach (range(1, 7) as $i) {
        ingest(['title' => 'Driver job '.$i]);
    }

    $before = count(sentMessages());
    $sent = app(JobAlerts::class)->sendDue();

    $dm = collect(sentMessages())->slice($before)->firstWhere('chat_id', 103);
    expect($sent)->toBe(1)
        ->and($dm['text'])->toContain('New jobs for you')->toContain('Driver job 1')->toContain('Driver job 5')->not->toContain('Driver job 6')
        ->and(JobAlert::query()->whereNull('sent_at')->count())->toBe(0);

    ingest(['title' => 'Driver job 8']);
    expect(app(JobAlerts::class)->sendDue())->toBe(0);

    $this->travel(16)->minutes();
    expect(app(JobAlerts::class)->sendDue())->toBe(1);
});

test('alerts off, a banned member and a closed job are not sent', function (): void {
    $off = joinMember(104, '+971502220004');
    JobFilter::forMember($off)->forceFill(['alerts_on' => false])->save();
    $banned = joinMember(105, '+971502220005');
    JobFilter::forMember($banned);

    $job = ingest();
    $banned->forceFill(['banned_at' => now()])->save();

    expect(JobAlert::query()->where('member_id', $off->id)->count())->toBe(0);

    $job->forceFill(['status' => 'closed'])->save();
    expect(app(JobAlerts::class)->sendDue())->toBe(0)
        ->and(JobAlert::query()->whereNull('sent_at')->count())->toBe(0);
});

test('the alert is in the member\'s language', function (): void {
    $member = joinMember(106, '+971502220006', 'አማርኛ');
    JobFilter::forMember($member);
    ingest();

    app(JobAlerts::class)->sendDue();

    expect(collect(sentMessages())->where('chat_id', 106)->last()['text'])->toContain('አዲስ ሥራ ለእርስዎ')->toContain('ሹፌር በዱባይ');
});
