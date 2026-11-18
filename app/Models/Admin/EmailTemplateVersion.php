<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved version of a platform e-mail's words (Doc 15 Phase F2): kept, and can be restored. */
class EmailTemplateVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email_template_id', 'version', 'subject', 'body', 'button', 'status', 'created_by'];

    protected $casts = ['version' => 'integer'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
