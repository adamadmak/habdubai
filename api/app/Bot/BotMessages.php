<?php

namespace App\Bot;

use App\Models\BotText;

/**
 * Every bot message goes through here: the admin's override wins, the
 * default in resources/bot/texts.php is the fallback, English is the last
 * resort when a language is missing. Button presses are matched the same
 * way, across every language in Choices::LANGUAGES, so an edited label
 * still works and a member who switched language mid-flow is understood.
 */
class BotMessages
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $defaults = null;

    /** @var array<string, array<string, string>>|null */
    private static ?array $overrides = null;

    public function text(string $key, string $language, array $vars = []): string
    {
        $text = $this->raw($key, $language);

        foreach ($vars as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /** A button label; same lookup, named apart so the admin page can group them. */
    public function button(string $key, string $language, array $vars = []): string
    {
        return $this->text($key, $language, $vars);
    }

    /**
     * The menu under a member's private chat: the everyday commands as six
     * buttons, three to a row, kept on screen until a flow shows a keyboard of
     * its own; the flow puts this one back when it ends.
     */
    public function menuKeyboard(string $language): array
    {
        $labels = array_map(fn (string $key) => ['text' => $this->button($key, $language)], array_keys(Home::MENU));

        return ['keyboard' => array_chunk($labels, 3), 'resize_keyboard' => true, 'is_persistent' => true];
    }

    /** True when the pressed text is this button's label in any language. */
    public function pressed(string $key, ?string $text, array $vars = []): bool
    {
        $text = trim((string) $text);

        if ($text === '') {
            return false;
        }

        foreach (Choices::LANGUAGES as $language) {
            if ($text === trim($this->text($key, $language, $vars))) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, string>> key => [language => default text] */
    public static function defaults(): array
    {
        return self::$defaults ??= require resource_path('bot/texts.php');
    }

    /** @return array<string, array<string, string>> key => [language => text], overrides applied */
    public static function all(): array
    {
        $all = self::defaults();

        foreach (self::overrides() as $key => $languages) {
            foreach ($languages as $language => $text) {
                $all[$key][$language] = $text;
            }
        }

        return $all;
    }

    public static function flush(): void
    {
        self::$overrides = null;
    }

    private function raw(string $key, string $language): string
    {
        $overrides = self::overrides();
        $defaults = self::defaults();

        return $overrides[$key][$language]
            ?? $defaults[$key][$language]
            ?? $overrides[$key]['en']
            ?? $defaults[$key]['en']
            ?? $key;
    }

    /** @return array<string, array<string, string>> */
    private static function overrides(): array
    {
        if (self::$overrides === null) {
            self::$overrides = [];

            try {
                foreach (BotText::query()->get(['key', 'language', 'text']) as $row) {
                    self::$overrides[$row->key][$row->language] = $row->text;
                }
            } catch (\Throwable) {
                // No table yet: defaults only.
            }
        }

        return self::$overrides;
    }
}
