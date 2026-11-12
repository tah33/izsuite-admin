<?php

namespace App\Services\Shared;

use Illuminate\Database\Eloquent\Model;

/**
 * A sales document, purchase or expense, as its comments and its activity
 * timeline need it (Doc 14 Phase D): the record, its app family, the
 * permission that views it, what the log calls it ("invoice INV-00001") and
 * its page.
 */
final class DiscussedRecord
{
    public function __construct(
        public readonly Model $model,
        public readonly string $family,
        public readonly string $permission,
        public readonly string $label,
        public readonly string $link,
    ) {}
}
