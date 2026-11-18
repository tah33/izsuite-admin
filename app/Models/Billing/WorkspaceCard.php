<?php

namespace App\Models\Billing;

use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's card for paying izSuite (Doc 13 sections 11 and 15 - Phase
 * D2): the provider's customer and card, in the provider's test or live
 * mode, and what the Subscription page shows of it. The card itself stays
 * with the provider; izSuite keeps only its brand, last four and expiry.
 * Without a card (removed), the customer is kept for the next one.
 */
class WorkspaceCard extends Model
{
    private const BRANDS = [
        'amex'       => 'American Express',
        'cartes_bancaires' => 'Cartes Bancaires',
        'diners'     => 'Diners Club',
        'discover'   => 'Discover',
        'jcb'        => 'JCB',
        'mastercard' => 'Mastercard',
        'unionpay'   => 'UnionPay',
        'visa'       => 'Visa',
    ];

    protected $fillable = [
        'workspace_id',
        'provider',
        'livemode',
        'customer_id',
        'card_id',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
    ];

    protected $casts    = [
        'livemode'  => 'boolean',
        'exp_month' => 'integer',
        'exp_year'  => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function hasCard(): bool
    {
        return $this->card_id !== null;
    }

    /** The brand as people know it: "visa" is "Visa". */
    public function brandName(): string
    {
        return self::brandOf($this->brand);
    }

    /** "Visa ending 4242" */
    public function label(): string
    {
        return self::describe($this->brand, $this->last4);
    }

    public static function brandOf(?string $brand): string
    {
        return self::BRANDS[$brand] ?? ($brand ? ucfirst($brand) : 'Card');
    }

    public static function describe(?string $brand, ?string $last4): string
    {
        return self::brandOf($brand).($last4 ? " ending {$last4}" : '');
    }
}
