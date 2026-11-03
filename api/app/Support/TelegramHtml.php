<?php

namespace App\Support;

/**
 * Telegram's HTML parse mode refuses a whole message over one stray "<" or an
 * unclosed tag, and a bot text that fails to send stops a flow halfway. Texts
 * edited in the admin are checked here before they are saved.
 */
final class TelegramHtml
{
    /** The tags Telegram accepts in HTML mode. */
    private const TAGS = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'span', 'tg-spoiler', 'a', 'tg-emoji', 'code', 'pre', 'blockquote'];

    /** Why Telegram would refuse the text, or null when it is fine. */
    public static function problem(string $html): ?string
    {
        preg_match_all('#<(/?)([a-zA-Z][a-zA-Z0-9-]*)(?:\s[^<>]*)?>#', $html, $tags, PREG_SET_ORDER);

        $rest = preg_replace('#<(/?)([a-zA-Z][a-zA-Z0-9-]*)(?:\s[^<>]*)?>#', '', $html) ?? '';
        if (str_contains($rest, '<') || str_contains($rest, '>')) {
            return 'Write < as &lt; and > as &gt; outside a tag.';
        }
        if (preg_match('/&(?![a-zA-Z]+;|#\d+;|#x[0-9a-fA-F]+;)/', $rest)) {
            return 'Write & as &amp;.';
        }

        $open = [];
        foreach ($tags as [, $closing, $name]) {
            $name = strtolower($name);
            if (! in_array($name, self::TAGS, true)) {
                return "Telegram does not allow the <{$name}> tag.";
            }
            if ($closing === '') {
                $open[] = $name;
            } elseif (array_pop($open) !== $name) {
                return "The </{$name}> tag closes something it did not open.";
            }
        }

        return $open === [] ? null : 'The <'.end($open).'> tag is never closed.';
    }
}
