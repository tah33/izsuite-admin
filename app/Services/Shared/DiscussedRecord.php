<?php

namespace App\Services\Shared;

use App\Models\User\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A sales document, purchase, expense or lead, as its comments and its
 * activity timeline need it (Doc 14 Phase D): the record, its app family,
 * the permission that views it, what the log calls it ("invoice INV-00001")
 * and its page. A record that not everyone with that permission sees - a
 * lead, by its owner and teams (Doc 17 Phase B2b) - says who else does.
 */
final class DiscussedRecord
{
    /** @param  (Closure(User): bool)|null  $sees  whether someone with the permission, at its location, sees it too */
    public function __construct(
        public readonly Model $model,
        public readonly string $family,
        public readonly string $permission,
        public readonly string $label,
        public readonly string $link,
        public readonly ?Closure $sees = null,
    ) {}
}
