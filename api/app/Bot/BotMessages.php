<?php

namespace App\Bot;

use App\Models\BotText;

/**
 * Every bot message goes through here: the admin's override wins, the
 * default in resources/bot/texts.php is the fallback, English is the last
 * resort when a language is missing. Button presses are matched the same
 * way, across all three languages, so an edited label still works.
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
