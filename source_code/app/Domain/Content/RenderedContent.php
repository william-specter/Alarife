<?php

namespace App\Domain\Content;

class RenderedContent
{
    /**
     * @param  array<int, array{id: string, text: string, level: int}>  $toc
     */
    public function __construct(
        public readonly string $html,
        public readonly array $toc = [],
        public readonly int $words = 0,
        public readonly string $plainText = '',
    ) {}

    public function readingMinutes(): int
    {
        return max(1, (int) ceil($this->words / 220));
    }
}
