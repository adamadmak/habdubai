<?php

namespace App\Ai;

final class AssistantAnswer
{
    /** @param array<int, array{title: string, url: ?string}> $sources */
    public function __construct(
        public readonly string $text,
        public readonly array $sources,
        public readonly bool $askCommunity,
        public readonly string $by,
    ) {}
}
