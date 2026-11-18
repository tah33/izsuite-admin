<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An announcement someone has put away for themselves (Doc 15 Phase F1). */
class AnnouncementDismissal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['announcement_id', 'user_id'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
