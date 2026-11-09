<?php

namespace App\Http\Resources\Billing;

use App\Models\Billing\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of izSuite's invoices to the business (Doc 13 section 11's Billing
 * History): its number, plan and period, what it comes to, when it is due or
 * was paid, its receipt, and the business's transfer reference. The invoice
 * still to be paid comes with how to pay it.
 *
 * @property SubscriptionInvoice $resource
 */
class SubscriptionInvoiceResource extends JsonResource
{
    public function __construct($resource, private bool $withInstructions = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'number'             => $this->number,
            'package'            => $this->package_name,
            'cycle'              => $this->cycle,
            'period_starts_on'   => $this->period_starts_on?->toDateString(),
            'period_ends_on'     => $this->period_ends_on?->toDateString(),
            'currency'           => $this->currency,
            'total'              => $this->total,
            'status'             => $this->status,
            'issued_on'          => $this->issued_on->toDateString(),
            'due_on'             => $this->due_on->toDateString(),
            'paid_on'            => $this->paid_at?->toDateString(),
            'receipt_number'     => $this->receipt_number,
            'transfer_reference' => $this->transfer_reference,
            ...($this->withInstructions ? ['payment_instructions' => $this->payment_instructions] : []),
        ];
    }
}
