<?php

use App\Models\Business;
use App\Models\BusinessOwner;
use App\Models\DirectoryCity;
use App\Models\Job;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function hiringOwner(int $telegramId = 7001, string $language = 'en'): BusinessOwner
{
    return BusinessOwner::query()->create(['telegram_user_id' => $telegramId, 'display_name' => 'Meron', 'language' => $language]);
}

function hiringBusiness(BusinessOwner $owner, array $overrides = []): Business
{
    return Business::query()->create(array_merge([
        'name' => 'Selam Cafe',
        'category' => 'cafe',
        'city_id' => DirectoryCity::query()->where('key', 'sharjah')->value('id'),
        'area' => 'Al Nahda',
        'whatsapp' => '+971501112233',
        'status' => 'approved',
        'verified' => true,
        'owner_id' => $owner->id,
    ], $overrides));
}

function postJob(BusinessOwner $owner, Business $business, array $overrides = [])
{
    Sanctum::actingAs($owner, ['owner']);

    return test()->postJson('/api/owner/jobs', array_merge([
        'business_id' => $business->id,
        'title' => 'Barista',
        'category' => 'hospitality',
        'description' => 'Morning shift in our cafe. Two years of experience with espresso machines.',
        'salary' => 2800,
        'accommodation' => true,
        'contact' => '0501112233',
    ], $overrides));
}

it('publishes a clean job from a verified business, with what the owner stated', function () {
    $owner = hiringOwner();
    $business = hiringBusiness($owner);

    postJob($owner, $business)->assertCreated();

    $job = Job::query()->sole();
    expect($job->status)->toBe('published')
        ->and($job->business_id)->toBe($business->id)
        ->and($job->company)->toBe('Selam Cafe')
        ->and($job->title)->toBe('Barista')
        ->and($job->category)->toBe('hospitality')
        ->and($job->emirate)->toBe('sharjah')
        ->and($job->sub_area)->toBe('Al Nahda')
        ->and($job->salary_min)->toBe(2800)
        ->and($job->accommodation)->toBe('yes')
        ->and($job->url)->toBeNull()
        ->and($job->apply_contact)->toBe('https://wa.me/971501112233');
});

it('tells the owner in the bot when the job goes live', function () {
    $owner = hiringOwner();

    postJob($owner, hiringBusiness($owner))->assertCreated();

    expect(lastTo(7001)['text'])->toContain('Barista')->toContain('live');
});

it('holds a job from an unverified business for a moderator', function () {
    $owner = hiringOwner();

    postJob($owner, hiringBusiness($owner, ['verified' => false]))->assertCreated();

    $job = Job::query()->sole();
    expect($job->status)->toBe('review')->and($job->review_reason)->toContain('not verified');
    $this->getJson('/api/public/jobs/'.$job->id)->assertNotFound();
});

it('holds a verified business that asks applicants for a fee', function () {
    $owner = hiringOwner();

    postJob($owner, hiringBusiness($owner), ['description' => 'Good job in our cafe. You must pay a visa fee of 2000 before you start work.'])->assertCreated();

    expect(Job::query()->sole()->status)->toBe('review');
});

it('refuses an owner without an approved listing, and someone else\'s listing', function () {
    $owner = hiringOwner();
    postJob($owner, hiringBusiness($owner, ['status' => 'pending']))->assertStatus(422)->assertJsonValidationErrors('business_id');

    $other = hiringOwner(7002);
    postJob($owner, hiringBusiness($other, ['name' => 'Other Place']))->assertStatus(422)->assertJsonValidationErrors('business_id');

    expect(Job::query()->count())->toBe(0);
});

it('asks for a usable way to apply', function () {
    $owner = hiringOwner();
    postJob($owner, hiringBusiness($owner), ['contact' => 'not a number!'])->assertStatus(422)->assertJsonValidationErrors('contact');
});

it('accepts a Telegram username as the way to apply', function () {
    $owner = hiringOwner();
    postJob($owner, hiringBusiness($owner), ['contact' => '@selam_cafe'])->assertCreated();

    expect(Job::query()->sole()->apply_contact)->toBe('https://t.me/selam_cafe');
});

it('lets one business post several different jobs, and refuses the same one twice', function () {
    $owner = hiringOwner();
    $business = hiringBusiness($owner);

    postJob($owner, $business)->assertCreated();
    postJob($owner, $business, ['title' => 'Cleaner', 'category' => 'cleaning'])->assertCreated();
    postJob($owner, $business)->assertStatus(422)->assertJsonValidationErrors('title');

    expect(Job::query()->where('status', 'published')->count())->toBe(2);
});

it('lets a filled position be posted again later', function () {
    $owner = hiringOwner();
    $business = hiringBusiness($owner);

    postJob($owner, $business)->assertCreated();
    $this->postJson('/api/owner/jobs/'.Job::query()->sole()->id.'/close')->assertOk();
    postJob($owner, $business)->assertCreated();

    expect(Job::query()->where('status', 'published')->count())->toBe(1)
        ->and(Job::query()->where('status', 'duplicate')->count())->toBe(0);
});

it('limits how many a day one owner may post', function () {
    $owner = hiringOwner();
    $business = hiringBusiness($owner);

    foreach (range(1, 5) as $i) {
        postJob($owner, $business, ['title' => 'Position number '.$i])->assertCreated();
        $this->travel(1)->minutes();
    }

    postJob($owner, $business, ['title' => 'One more position'])->assertStatus(422)->assertJsonValidationErrors('title');
});

it('sends an applicant to the chat, counts the tap, and says why they are writing', function () {
    $owner = hiringOwner();
    postJob($owner, hiringBusiness($owner))->assertCreated();
    $job = Job::query()->sole();

    $page = $this->getJson('/api/public/jobs/'.$job->id)->assertOk()->json('job');
    expect($page['direct'])->toBeTrue()
        ->and($page['apply_via'])->toBe('whatsapp')
        ->and($page['business']['name'])->toBe('Selam Cafe')
        ->and($page['source'])->toBeNull()
        ->and($page)->not->toHaveKey('apply_contact');

    $target = $this->get('/api/public/jobs/'.$job->id.'/apply')->assertRedirect()->headers->get('Location');
    expect($target)->toStartWith('https://wa.me/971501112233?text=')->toContain('Barista')
        ->and($job->fresh()->clicks)->toBe(1);
});

it('shows a place\'s open positions on its page', function () {
    $owner = hiringOwner();
    $business = hiringBusiness($owner);
    postJob($owner, $business)->assertCreated();

    $jobs = $this->getJson('/api/public/businesses/'.$business->fresh()->slug)->assertOk()->json('jobs');

    expect($jobs)->toHaveCount(1)->and($jobs[0]['title'])->toBe('Barista');
});

it('closes a filled position, and takes back one in review without making it public', function () {
    $owner = hiringOwner();
    $live = hiringBusiness($owner);
    postJob($owner, $live)->assertCreated();
    $job = Job::query()->sole();

    $this->postJson('/api/owner/jobs/'.$job->id.'/close')->assertOk();
    expect($job->fresh()->status)->toBe('closed');

    $unchecked = hiringBusiness($owner, ['name' => 'New Place', 'verified' => false]);
    postJob($owner, $unchecked)->assertCreated();
    $held = Job::query()->where('business_id', $unchecked->id)->sole();

    $this->postJson('/api/owner/jobs/'.$held->id.'/close')->assertOk();
    expect($held->fresh()->status)->toBe('rejected');
    $this->getJson('/api/public/jobs/'.$held->id)->assertNotFound();

    $listed = $this->getJson('/api/owner/jobs')->assertOk()->json('jobs');
    expect(collect($listed)->pluck('id')->all())->toBe([$job->id]);
});

it('lets nobody close another business\'s job', function () {
    $owner = hiringOwner();
    postJob($owner, hiringBusiness($owner))->assertCreated();

    app('auth')->forgetGuards();
    Sanctum::actingAs(hiringOwner(7002), ['owner']);
    $this->postJson('/api/owner/jobs/'.Job::query()->sole()->id.'/close')->assertNotFound();

    expect(Job::query()->sole()->status)->toBe('published');
});

it('closes a business\'s jobs when a moderator hides it', function () {
    $owner = hiringOwner();
    $business = hiringBusiness($owner);
    postJob($owner, $business)->assertCreated();

    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(), ['admin']);
    $this->postJson('/api/admin/businesses/'.$business->id.'/hide', ['note' => 'Reported'])->assertOk();

    expect(Job::query()->sole()->status)->toBe('closed');
});

it('tells the owner when a moderator refuses, and approval still works', function () {
    $owner = hiringOwner();
    postJob($owner, hiringBusiness($owner, ['verified' => false]))->assertCreated();
    $job = Job::query()->sole();

    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(), ['admin']);
    $this->getJson('/api/admin/jobs/'.$job->id)->assertOk()
        ->assertJsonPath('job.business.name', 'Selam Cafe')
        ->assertJsonPath('job.apply_contact', 'https://wa.me/971501112233');

    $this->postJson('/api/admin/jobs/'.$job->id.'/reject', ['reason' => 'We could not confirm this position.'])->assertOk();
    expect(lastTo(7001)['text'])->toContain('not posted')->toContain('could not confirm');

    $this->postJson('/api/admin/jobs/'.$job->id.'/approve')->assertOk();
    expect($job->fresh()->status)->toBe('published')->and(lastTo(7001)['text'])->toContain('live');
});

it('lists an owner\'s approved listings with a contact ready for the form', function () {
    $owner = hiringOwner();
    hiringBusiness($owner);
    hiringBusiness($owner, ['name' => 'Waiting Place', 'status' => 'pending']);
    Sanctum::actingAs($owner, ['owner']);

    $data = $this->getJson('/api/owner/jobs')->assertOk()->json();

    expect($data['businesses'])->toHaveCount(1)
        ->and($data['businesses'][0]['contact'])->toBe('+971501112233')
        ->and($data['waiting'])->toBeTrue();
});

it('asks the owner to sign in', function () {
    $this->postJson('/api/owner/jobs', [])->assertUnauthorized();
});
