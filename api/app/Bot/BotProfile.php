<?php

namespace App\Bot;

use App\Models\PlatformSetting;
use App\Services\TelegramBotService;
use App\Support\PublicUrls;
use Illuminate\Support\Facades\Cache;

/**
 * How the bot presents itself in Telegram: the description on an empty chat,
 * the command menu in each language, and the Open button beside the message
 * box. Telegram holds all of this, so it is sent when it changes: the first
 * message after a deploy that changed a word, a new website address or a new
 * bot brings it up to date. Nobody has to remember to.
 */
class BotProfile
{
    public const SYNCED_KEY = 'bot_profile_synced';

    /** Each member's own menu follows, a few at a time: see Home::refreshChats. */
    public const CHATS_CURSOR_KEY = 'bot_profile_chats_after';

    /** Telegram refusing must not repeat on every message, and must not be the last word either. */
    private const RETRY_SECONDS = 300;

    private const COMMANDS = ['jobs', 'goods', 'ask', 'directory', 'safe', 'profile', 'language', 'help'];

    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly BotMessages $messages,
    ) {}

    /** @return array<int, array{command: string, description: string}> */
    public function commands(string $language): array
    {
        return array_map(fn (string $command) => ['command' => $command, 'description' => $this->messages->text('cmd_'.$command, $language)], self::COMMANDS);
    }

    /** At most once per change, and never in the way of the message being handled. */
    public function syncIfStale(): void
    {
        try {
            $fingerprint = $this->fingerprint();

            if (PlatformSetting::valueFor(self::SYNCED_KEY) === $fingerprint) {
                return;
            }

            // One attempt at a time: two messages arriving together do not both send all of this.
            if (! Cache::add('bot-profile-sync', true, self::RETRY_SECONDS)) {
                return;
            }

            // Written only when all of it landed: a process killed half way, or one refusal, tries again.
            if ($this->sync()) {
                PlatformSetting::put(self::SYNCED_KEY, $fingerprint);
                PlatformSetting::put(self::CHATS_CURSOR_KEY, '0');
                Cache::forget('bot-profile-sync');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** False at the first refusal. */
    public function sync(): bool
    {
        if (! $this->telegram->setCommands($this->commands('en'))) {
            return false;
        }

        foreach (Choices::LANGUAGES as $language) {
            if (! $this->telegram->setCommands($this->commands($language), null, $language)
                || ! $this->telegram->setDescription($this->messages->text('bot_description', $language), $this->messages->text('bot_short_description', $language), $language)) {
                return false;
            }
        }

        if (! $this->telegram->setDescription($this->messages->text('bot_description', 'en'), $this->messages->text('bot_short_description', 'en'))) {
            return false;
        }

        return ! PublicUrls::siteIsPublic() || $this->telegram->setMenuButton(null, $this->messages->button('btn_menu_open', 'en'), PublicUrls::site());
    }

    private function fingerprint(): string
    {
        $words = [];
        foreach (Choices::LANGUAGES as $language) {
            $words[] = [$this->commands($language), $this->messages->text('bot_description', $language), $this->messages->text('bot_short_description', $language)];
        }

        return sha1((string) json_encode([$words, PublicUrls::site(), $this->telegram->tokenFingerprint()]));
    }
}
