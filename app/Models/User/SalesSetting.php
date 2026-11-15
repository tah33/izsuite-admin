<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings > Sales (Doc 11 section 15): what a new quotation, proforma invoice
 * or invoice starts with. Changing it leaves documents already made as they
 * are. A business has none until it first saves them; an unsaved one reads the
 * defaults, the same as the columns'.
 */
class SalesSetting extends Model
{
    /** What a new sale starts as (Doc 16 Phase H1): the Dashboard's and a contact's New Sale open it. */
    public const START_AS = ['invoice', 'quotation', 'sales_order'];

    /** After a partial delivery (Doc 16 Phase H1): a backorder asked for each time, always made, or never. */
    public const BACKORDERS = ['ask', 'always', 'never'];

    protected $fillable = [
        'payment_terms',
        'quotation_validity',
        'proforma_validity',
        'line_discounts',
        'document_discounts',
        // The full Sales app's (Doc 16 Phase B): its orders invoice what was ordered or what was delivered.
        'invoice_policy',
        // Doc 16 Phase D1: advances recorded, and overpayments kept as them.
        'customer_advances',
        // Doc 16 Phase G1: confirmed orders not invoiced yet count toward a customer's credit exposure.
        'credit_counts_orders',
        // Doc 16 Phase G2: the days a new secure link lasts (null: it never expires), and whether customers comment through it.
        'link_expiry_days',
        'link_comments',
        // Doc 16 Phase H1: what a new sale starts as, the price list tried after the others, a deposit's
        // starting percentage, the backorder rule, the shipping terms new documents start with, and
        // whether whoever makes a document is its salesperson.
        'new_sales_start_as',
        'default_price_list_id',
        'deposit_percent',
        'backorders',
        'default_shipping_terms',
        'salesperson_is_creator',
        'notes',
        'terms',
    ];

    protected $attributes = [
        'payment_terms'          => 0,
        'quotation_validity'     => 30,
        'proforma_validity'      => 30,
        'line_discounts'         => true,
        'document_discounts'     => true,
        'invoice_policy'         => 'ordered',
        'customer_advances'      => true,
        'credit_counts_orders'   => false,
        'link_comments'          => true,
        'new_sales_start_as'     => 'invoice',
        'backorders'             => 'ask',
        'salesperson_is_creator' => false,
    ];

    protected $casts = [
        'payment_terms'          => 'integer',
        'quotation_validity'     => 'integer',
        'proforma_validity'      => 'integer',
        'line_discounts'         => 'boolean',
        'document_discounts'     => 'boolean',
        'customer_advances'      => 'boolean',
        'credit_counts_orders'   => 'boolean',
        'link_expiry_days'       => 'integer',
        'link_comments'          => 'boolean',
        'default_price_list_id'  => 'integer',
        'deposit_percent'        => 'decimal:2',
        'salesperson_is_creator' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The list tried after the others (Doc 16 Phase H1). */
    public function defaultPriceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'default_price_list_id');
    }
}
