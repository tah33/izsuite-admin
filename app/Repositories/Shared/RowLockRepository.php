<?php

namespace App\Repositories\Shared;

use Illuminate\Database\Eloquent\Model;

/**
 * A record read again and locked until its transaction ends (Doc 14 section
 * 22): what it says now, and no one else changing it meanwhile - so two
 * payments sent at the same moment cannot both take what is still due.
 */
class RowLockRepository
{
    /**
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    public function lock(Model $model): Model
    {
        return $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
    }
}
