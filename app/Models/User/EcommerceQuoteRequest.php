<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's request for a quotation on a storefront (Doc 19 section 19.30
 * - Phase H4a), numbered QR-00001: who asked - and the account they were
 * signed in to - the products they asked about, where they are to be
 * delivered, the date wanted and their message. It is the business's sales
 * enquiry, never an order, a sale or a receivable. From Phase H4b, as
 * Settings → eCommerce chooses, it also becomes a CRM lead (while CRM is on)
 * or, from Phase H4c, a draft quotation.
 */
class EcommerceQuoteRequest extends Model
{
    /** The business's sequence its numbers come from (WorkspaceSequence). */
    public const NUMBER_SEQUENCE = 'ecommerce_quote_request';

    public const NUMBER_PREFIX = 'QR-';

    /** How many products one request may ask about. */
    public const MAX_ITEMS = 20;

    protected $fillable = [
        'storefront_id',
        'storefront_customer_id',
        // Phase H4b: the CRM lead it became.
        'crm_lead_id',
        // Phase H4c: the draft quotation it became.
        'sales_document_id',
        'number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'company',
        'delivery_country',
        'delivery_city',
        'delivery_address',
        'required_on',
        'message',
    ];

    protected $casts = [
        'storefront_id'          => 'integer',
        'storefront_customer_id' => 'integer',
        'crm_lead_id'            => 'integer',
        'sales_document_id'      => 'integer',
        'required_on'            => 'date',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    /** Its products, in the order they were asked about. */
    public function items(): HasMany
    {
        return $this->hasMany(EcommerceQuoteRequestItem::class)->orderBy('position')->orderBy('id');
    }

    /** Phase H4c: the draft quotation it became, as Sales has it now. */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }
}
