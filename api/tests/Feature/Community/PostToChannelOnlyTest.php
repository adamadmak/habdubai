<?php

use App\Announcements\AnnouncementPublisher;
use App\Models\Announcement;
use App\Models\AnnouncementTopic;

function newsItem(): Announcement
{
    return Announcement::query()->create([
        'topic_id' => AnnouncementTopic::query()->where('key', 'safety')->value('id'),
        'original_language' => 'en',
        'text_en' => "A headline\n\nThe text <with> a character to escape.",
        'text_am' => "A headline\n\nThe text <with> a character to escape.",
        'text_ti' => "A headline\n\nThe text <with> a character to escape.",
        'status' => 'published',
        'published_at' => '2026-10-24 00:00:00',
    ]);
}

it('posts to the channel and nothing else: no messages, no pin, same date', function () {
    jobsChannel('@habuae_test');
    // Members are subscribed to the safety topic by default, so a message to them would show.
    joinMember(7101, '+971501234567');
    $before = count(sentMessages());
    $item = newsItem();

    expect(app(AnnouncementPublisher::class)->postToChannelOnly($item))->toBeTrue();

    $new = array_slice(sentMessages(), $before);
    expect($new)->toHaveCount(1)
        ->and($new[0]['chat_id'])->toBe('@habuae_test')
        ->and($new[0]['text'])->toContain('A headline')->toContain('&lt;with&gt;')
        ->and(telegramCalls('pinChatMessage'))->toBe([])
        ->and($item->fresh()->channel_message_id)->not->toBeNull()
        ->and($item->fresh()->published_at->toDateString())->toBe('2026-10-24');
});

it('does not post the same item twice', function () {
    jobsChannel('@habuae_test');
    $item = newsItem();
    $publisher = app(AnnouncementPublisher::class);

    expect($publisher->postToChannelOnly($item))->toBeTrue()
        ->and($publisher->postToChannelOnly($item->fresh()))->toBeFalse()
        ->and(collect(sentMessages())->where('chat_id', '@habuae_test'))->toHaveCount(1);
});

it('leaves an item unposted when Telegram refuses, so it can be tried again', function () {
    jobsChannel('@habuae_test');
    TelegramFake::$errors['sendMessage'] = [400, 'Bad Request: chat not found'];
    $item = newsItem();

    expect(app(AnnouncementPublisher::class)->postToChannelOnly($item))->toBeFalse()
        ->and($item->fresh()->channel_message_id)->toBeNull();
});

it('does nothing when no channel is set', function () {
    expect(app(AnnouncementPublisher::class)->postToChannelOnly(newsItem()))->toBeFalse()
        ->and(sentMessages())->toBe([]);
});
