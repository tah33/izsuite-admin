<?php

namespace App\Services\Documents;

/** A document drawn up: its page, the footer every PDF page gets, its paper and the name its PDF is saved under. */
final class RenderedDocument
{
    public function __construct(
        public readonly string $html,
        public readonly string $footer,
        public readonly string $paper,
        public readonly string $filename,
    ) {}
}
