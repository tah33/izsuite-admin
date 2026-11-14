<?php

namespace App\Repositories\Admin;

use App\Models\Admin\ConsentRecord;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;

/** Consent records (Doc 15 Phase G3): written once, read newest first. */
class ConsentRecordRepository
{
    public function create(array $data): ConsentRecord
    {
        return ConsentRecord::create($data);
    }

    /** $user's records, newest first. */
    public function forUser(User $user, int $limit = 50): Collection
    {
        return ConsentRecord::query()->where('user_id', $user->id)->orderByDesc('id')->limit($limit)->get();
    }
}
