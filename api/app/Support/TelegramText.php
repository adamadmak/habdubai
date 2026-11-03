<?php

namespace App\Support;

/**
 * Telegram refuses a message longer than 4096 characters (a caption longer
 * than 1024), and refuses an HTML message whole when a cut leaves half a tag,
 * half an entity or an unclosed tag. These helpers cut HTML where a reader
 * would, at a line break (else a space), never inside a tag or an entity, and
 * close whatever the cut left open.
 *
 * Lengths are counted the way Telegram counts them at worst: tags and
 * entities included, and an emoji outside the basic plane as two (UTF-16), so
 * a part that passes here always passes there.
 */
final class TelegramText
{
    public const MAX_TEXT = 4096;

    public const MAX_CAPTION = 1024;

    /** The most messages one long text becomes before the rest is cut off. */
    public const MAX_PARTS = 5;

    /** At most $limit characters, with "…" where it was cut. */
    public static function clip(string $html, int $limit = self::MAX_TEXT): string
    {
        if (self::length($html) <= $limit) {
            return $html;
        }

        return self::cut(self::tokens($html), $limit, '…')[0];
    }

    /**
     * The text as messages that each fit, in order. A tag open across a cut is
     * closed at the end of one part and opened again at the start of the next.
     *
     * @return array<int, string>
     */
    public static function chunks(string $html, int $limit = self::MAX_TEXT, int $maxParts = self::MAX_PARTS): array
    {
        if (self::length($html) <= $limit) {
            return [$html];
        }

        $parts = [];
        $tokens = self::tokens($html);

        while ($tokens !== []) {
            $last = count($parts) >= $maxParts - 1;
            [$head, $tokens] = self::cut($tokens, $limit, $last ? '…' : '');
            if (trim($head) !== '') {
                $parts[] = $head;
            }
            if ($last) {
                break;
            }
        }

        return $parts === [] ? [self::clip($html, $limit)] : $parts;
    }

    /** Telegram's count, at worst: UTF-16 units, so an emoji outside the basic plane is two. */
    public static function length(string $text): int
    {
        return mb_strlen($text) + (int) preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $text);
    }

    /**
     * The longest head that fits (with its open tags closed and $marker before
     * them), and the tokens left over (with those tags opened again).
     *
     * @param  array<int, array{s: string, len: int, open: ?string, close: ?string}>  $tokens
     * @return array{0: string, 1: array<int, array{s: string, len: int, open: ?string, close: ?string}>}
     */
    private static function cut(array $tokens, int $limit, string $marker): array
    {
        $total = array_sum(array_column($tokens, 'len'));
        if ($total <= $limit) {
            return [implode('', array_column($tokens, 's')), []];
        }

        $markerLen = self::length($marker);
        $stack = [];
        $used = 0;
        $line = $space = $hard = null;

        foreach ($tokens as $i => $token) {
            $after = self::apply($stack, $token);
            if ($used + $token['len'] + self::closingLength($after) + $markerLen > $limit) {
                break;
            }

            $stack = $after;
            $used += $token['len'];
            $point = ['at' => $i + 1, 'stack' => $stack, 'used' => $used];

            if ($token['s'] === "\n") {
                $line = $point;
            } elseif ($token['s'] === ' ') {
                $space = $point;
            }
            $hard = $point;
        }

        // Nothing fits (one enormous tag): take it anyway rather than loop forever.
        $hard ??= ['at' => 1, 'stack' => self::apply([], $tokens[0]), 'used' => $tokens[0]['len']];

        // A cut at a line break or a space, unless that would throw away more than half.
        $half = intdiv($hard['used'], 2);
        $point = match (true) {
            $line !== null && $line['used'] >= $half => $line,
            $space !== null && $space['used'] >= $half => $space,
            default => $hard,
        };

        $head = rtrim(implode('', array_column(array_slice($tokens, 0, $point['at']), 's')));
        foreach (array_reverse($point['stack']) as $open) {
            $marker .= '</'.$open['name'].'>';
        }

        $rest = array_slice($tokens, $point['at']);
        while ($rest !== [] && in_array($rest[0]['s'], ["\n", ' ', "\t", "\r"], true)) {
            array_shift($rest);
        }
        if ($rest !== []) {
            $reopen = array_map(fn (array $open) => self::token($open['tag']), $point['stack']);
            $rest = [...$reopen, ...$rest];
        }

        return [$head.$marker, $rest];
    }

    /**
     * @param  array<int, array{name: string, tag: string}>  $stack
     * @param  array{s: string, len: int, open: ?string, close: ?string}  $token
     * @return array<int, array{name: string, tag: string}>
     */
    private static function apply(array $stack, array $token): array
    {
        if ($token['open'] !== null) {
            $stack[] = ['name' => $token['open'], 'tag' => $token['s']];
        } elseif ($token['close'] !== null) {
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i]['name'] === $token['close']) {
                    array_splice($stack, $i, 1);
                    break;
                }
            }
        }

        return $stack;
    }

    /** @param array<int, array{name: string, tag: string}> $stack */
    private static function closingLength(array $stack): int
    {
        $length = 0;
        foreach ($stack as $open) {
            $length += strlen($open['name']) + 3;
        }

        return $length;
    }

    /** @return array<int, array{s: string, len: int, open: ?string, close: ?string}> */
    private static function tokens(string $html): array
    {
        $tokens = [];
        // A stray byte (a scraped page, a copied name) would make the /u split fail and the
        // message go out empty; it is dropped instead.
        $html = (string) mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $pieces = preg_split('/(<[^<>]*>|&(?:[a-zA-Z]+|#\d+|#x[0-9a-fA-F]+);)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($pieces as $piece) {
            if (($piece[0] === '<' && str_ends_with($piece, '>')) || ($piece[0] === '&' && str_ends_with($piece, ';'))) {
                $tokens[] = self::token($piece);

                continue;
            }

            foreach (mb_str_split($piece) as $char) {
                $tokens[] = ['s' => $char, 'len' => strlen($char) === 4 ? 2 : 1, 'open' => null, 'close' => null];
            }
        }

        return $tokens;
    }

    /** @return array{s: string, len: int, open: ?string, close: ?string} */
    private static function token(string $piece): array
    {
        $open = $close = null;
        if (preg_match('#^<(/?)([a-zA-Z][a-zA-Z0-9-]*)#', $piece, $m)) {
            $m[1] === '' ? $open = strtolower($m[2]) : $close = strtolower($m[2]);
        }

        return ['s' => $piece, 'len' => self::length($piece), 'open' => $open, 'close' => $close];
    }
}
