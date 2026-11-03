<?php

use App\Models\EmployerReport;
use App\Models\FaqEntry;
use App\Models\PlatformSetting;

test('/faq lists topics with entries and opens a topic', function (): void {
    joinMember(520);
    FaqEntry::query()->create(['category' => 'visa', 'q_en' => 'Who pays for my visa?', 'a_en' => 'Your employer.', 'active' => true]);
    FaqEntry::query()->create(['category' => 'visa', 'q_en' => 'Hidden entry', 'a_en' => 'x', 'active' => false]);

    say(520, '/faq');
    $buttons = collect(lastMessage()['reply_markup']['inline_keyboard'])->flatten(1);
    expect($buttons->pluck('callback_data')->all())->toBe(['f:cat:visa'])->and($buttons->first()['text'])->toContain('(1)');

    pressQ(520, 'f:cat:visa');
    expect(collect(telegramCalls('editMessageText'))->last()['text'])->toContain('Who pays for my visa?')->not->toContain('Hidden entry');
});

test('/faq with a word searches', function (): void {
    joinMember(521);
    FaqEntry::query()->create(['category' => 'banking', 'q_en' => 'How do I open a bank account with a domestic worker visa?', 'a_en' => 'Bring your Emirates ID and salary letter.', 'active' => true]);

    say(521, '/faq bank account');
    expect(lastText())->toContain('How do I open a bank account');

    say(521, '/faq zebra');
    expect(lastText())->toContain('Nothing matches');
});

test('/safe shows emergency numbers, configured lines, and works even for non-members', function (): void {
    PlatformSetting::put('safety_consulate_phone', '+971 4 000 0000');
    joinMember(530);

    say(530, '/safe');
    expect(lastText())->toContain('999')->toContain('901')->toContain('Ethiopian consulate: +971 4 000 0000')->not->toContain('MoHRE:')
        ->and(lastMessage()['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('s:report');

    say(531, '/safe', ['language_code' => 'en']);
    expect(lastText())->toContain('999');
});

test('a private employer report is stored for moderators and nothing is posted', function (): void {
    $member = joinMember(532);

    pressQ(532, 's:report');
    say(532, 'Al Example Household');
    say(532, 'short');
    expect(lastText())->toContain('write a little more');
    say(532, 'They have not paid my salary for three months and keep my passport.');
    say(532, 'Yes');

    $report = EmployerReport::query()->firstOrFail();
    expect($report->member_id)->toBe($member->id)
        ->and($report->employer_name)->toBe('Al Example Household')
        ->and($report->contactable)->toBeTrue()
        ->and(lastText())->toContain('Only moderators see this')
        ->and(collect(sentMessages())->pluck('chat_id')->unique()->all())->toBe([532]);
});
