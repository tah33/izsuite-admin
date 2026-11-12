<?php

namespace App\Repositories\Shared;

use Illuminate\Database\Eloquent\Model;

/**
 * Who added a shared record and who last changed it (Doc 14 section 14.5 -
 * see RecordsAuthors), read with it for its own page.
 */
class RecordAuthorRepository
{
    public const WITH = ['creator:id,first_name,last_name', 'updater:id,first_name,last_name'];

    /**
     * @template T of Model
     *
     * @param  T  $record
     * @return T
     */
    public function load(Model $record): Model
    {
        return $record->loadMissing(self::WITH);
    }
}
