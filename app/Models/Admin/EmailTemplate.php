<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform e-mail written in the admin, in one language (Doc 15 section
 * 15.20 - Phase F2): its subject, message and button words, with variables
 * such as {plan}. Active, it is sent in place of the words built into the
 * code; a draft is kept, and not sent. Each save is a version.
 */
class EmailTemplate extends Model
{
    public const ACTIVE = 'active';

    public const DRAFT  = 'draft';

    protected $fillable = ['key', 'language', 'subject', 'body', 'button', 'status', 'version', 'updated_by'];

    protected $casts = ['version' => 'integer'];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class)->orderByDesc('version');
    }
}
