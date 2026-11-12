<?php

namespace App\Models\Concerns;

use App\Support\Authors;
use Illuminate\Database\Eloquent\Model;

/**
 * A shared record - a contact, item, payment account or location - that
 * writes down who added it and who last changed it (Doc 14 section 14.5),
 * whichever way it is saved: a form, an import, a deactivation. Work with
 * nobody behind it leaves them as they are.
 */
trait RecordsAuthors
{
    use HasAuthors;

    public static function bootRecordsAuthors(): void
    {
        static::creating(function (Model $model) {
            $model->created_by ??= Authors::current();
            $model->updated_by ??= Authors::current();
        });

        static::updating(function (Model $model) {
            if (($id = Authors::current()) !== null) {
                $model->updated_by = $id;
            }
        });
    }
}
