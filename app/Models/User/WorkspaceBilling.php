<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's billing details for its izSuite subscription (Doc 13 section
 * 11): where its izSuite invoices go - apart from its own business profile,
 * which its customers see.
 */
class WorkspaceBilling extends Model
{
    protected $table    = 'workspace_billing';

    protected $fillable = [
        'name',
        'email',
        'address',
        'country',
        'tax_id',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
