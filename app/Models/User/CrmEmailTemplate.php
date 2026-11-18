<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's template for its CRM e-mails (Doc 17 section 17.19 - Phase
 * F3b): a name, a subject and a message. Chosen on an e-mail's form, it
 * fills them in with its placeholders - PLACEHOLDERS - filled for the lead,
 * opportunity or contact and the sender; the e-mail keeps the text, so
 * changing or deleting a template changes no e-mail sent.
 */
class CrmEmailTemplate extends Model
{
    /** What a template can say, and what each stands for (CrmEmailService::placeholders()). */
    public const PLACEHOLDERS = ['contact_name', 'company_name', 'opportunity_name', 'user_name', 'business_name'];

    protected $fillable = [
        'name',
        'subject',
        'body',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
