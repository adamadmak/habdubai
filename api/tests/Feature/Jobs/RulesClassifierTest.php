<?php

use App\Jobs\Pipeline\RulesJobClassifier;
use App\Models\JobRaw;

function rules(string $title, string $text, ?string $location = null): \App\Jobs\Pipeline\Classification
{
    $raw = new JobRaw(['title' => $title, 'raw_text' => $title."\n".$text, 'location' => $location]);

    return app(RulesJobClassifier::class)->classify($raw);
}

test('plural trade words still pick the category', function (): void {
    expect(rules('Drivers wanted urgently', 'Several positions open.')->category)->toBe('driver')
        ->and(rules('Cleaners needed', 'Office cleaning.')->category)->toBe('cleaning')
        ->and(rules('Nurses for home care', 'Day shifts.')->category)->toBe('nursing');
});

test('a fee is not read as the salary', function (): void {
    $c = rules('Drivers wanted', 'Pay the registration fee of AED 500 to secure your visa. Salary AED 2,800 per month.');

    expect($c->salaryMin)->toBe(2800);

    $onlyFee = rules('Drivers wanted', 'Pay the registration fee of AED 500 to secure your visa.');
    expect($onlyFee->salaryMin)->toBeNull();
});

test('a salary written after "Pay:" is kept', function (): void {
    expect(rules('Barista', 'Pay: AED 3,000 per month, morning shift.')->salaryMin)->toBe(3000);
});

test('salary ranges, places and stated benefits are picked up', function (): void {
    $c = rules('Security guard', 'Mall in Sharjah. AED 2,300 - 2,600 monthly, free accommodation, visa provided.');

    expect($c->salaryMin)->toBe(2300)->and($c->salaryMax)->toBe(2600)
        ->and($c->emirate)->toBe('sharjah')
        ->and($c->accommodation)->toBe('yes')
        ->and($c->visa)->toBe('yes')
        ->and($c->by)->toBe('rules')
        ->and($c->summaryAm)->toBe('');
});

test('source validation errors use readable field names', function (): void {
    \Laravel\Sanctum\Sanctum::actingAs(admin(), ['admin']);

    $this->postJson('/api/admin/job-sources/test', ['kind' => 'html', 'config' => ['url' => 'https://example.com']])
        ->assertStatus(422)->assertJsonPath('errors', fn ($e) => ($e['config.item'][0] ?? '') === 'The item selector field is required.');
});
