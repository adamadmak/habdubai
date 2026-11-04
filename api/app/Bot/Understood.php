<?php

namespace App\Bot;

/** What a member's message was about: see Understand. */
final class Understood
{
    /**
     * @param  'jobs'|'places'|'goods'|'safety'|'hello'|'thanks'|null  $kind
     * @param  array<int, string>  $words  what is left once the known words are taken out
     */
    public function __construct(
        public readonly ?string $kind,
        public readonly ?string $trade,
        public readonly ?string $emirate,
        public readonly ?string $category,
        public readonly ?string $cityKey,
        public readonly array $words,
        /** A plain request rather than a question for the assistant. */
        public readonly bool $direct,
        /** A word that can mean guarding work or protection, with nothing to say which. */
        public readonly bool $mayBeTrouble = false,
    ) {}
}
