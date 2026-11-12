<?php

namespace App\Models\Concerns;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A record that keeps who added it (created_by) and who last changed it (updated_by). */
trait HasAuthors
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
