<?php

namespace App\Services\Shared\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * What a notification says (Doc 14 section 15 - Phase B1): what set it off,
 * the key that makes the same event reach a user once, its title and words,
 * the page that shows it, its record, and whether it also goes by e-mail.
 */
final class Notice
{
    public function __construct(
        public readonly string $trigger,
        public readonly string $key,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $link = null,
        public readonly ?Model $subject = null,
        public readonly bool $email = false,
    ) {}
}
