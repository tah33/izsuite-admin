<?php

namespace App\Services\Billing;

use App\Mail\SubscriptionBillingMail;
use App\Mail\SubscriptionNoticeMail;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Repositories\Admin\SettingRepository;
use App\Repositories\Billing\BillingSequenceRepository;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Repositories\Billing\SubscriptionPaymentRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Repositories\User\WorkspaceBillingRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notifier;
use App\Services\User\Apps\SyncAppsWithPackage;
use App\Support\Countries;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * izSuite's invoices to a business for its subscription (Doc 13 sections 11,
 * 13 and 15), as the business's Subscription page and the admin both use
 * them: issued with izSuite's own number, paid by bank transfer, and - once
 * izSuite confirms the money arrived - given a receipt number, which starts
 * the paid period on the plan. An invoice keeps who it was billed to, its
 * tax (Phase D1, SubscriptionTaxes), izSuite's details and the transfer
 * instructions as they were when issued.
 *
 * Each invoice and receipt goes by e-mail, PDF attached, to the billing
 * e-mail and the Business Owner.
 */
class SubscriptionBilling
{
    public const INVOICE_PREFIX = 'IZS-INV-';

    public const RECEIPT_PREFIX = 'IZS-RCT-';

    /** Days to pay an invoice when the admin has set none (Settings > Billing). */
    public const DUE_DAYS       = 7;

    /** Grace days a declined saved card is tried again on, besides the last, when the admin has set none (Phase D3). */
    public const RETRY_DAYS     = '1,3';

    /** How an invoice izSuite's credit covers in full is paid (Doc 15 Phase C2b). */
    public const PAID_WITH_CREDIT = 'izSuite credit';

    public function __construct(
        private SubscriptionInvoiceRepository $invoices,
        private SubscriptionPaymentRepository $payments,
        private WorkspaceSubscriptionRepository $subscriptions,
        private BillingSequenceRepository $sequences,
        private WorkspaceBillingRepository $billingDetails,
        private SettingRepository $settings,
        private PaymentMethodRepository $paymentMethods,
        private BusinessCalendar $calendar,
        private SyncAppsWithPackage $sync,
        private SubscriptionTaxes $taxes,
        private Notifier $notifier,
        private NotificationAudience $audience,
        private SubscriptionCredits $credits,
        private PackagePricing $pricing,
    ) {}

    /** $package's price for $cycle, as $workspace pays it today (Doc 15 Phase C3a: its currency's) - null while izSuite has not announced it. */
    public function price(Package $package, string $cycle, ?Workspace $workspace = null): ?string
    {
        return $this->pricing->price($package, $cycle, $workspace)['amount'] ?? null;
    }

    /**
     * What $package's $cycle comes to for $workspace if invoiced today: its
     * price, less an upgrade's $credit and the business's own credit from
     * izSuite (Doc 15 Phase C2b, `adjustment`), plus the tax on what is left
     * (Phase D1) - shown before a plan is chosen or changed, and what is
     * invoiced. $lock holds the business's credit while an invoice takes it.
     * The price is the one the business pays today, in its currency (Doc 15
     * Phase C3a) - or $price, a renewal's own.
     *
     * @param  array{currency: string, amount: string}|null  $price
     * @return array{currency: string, amount: string, credit: string, adjustment: string, tax: array{name: ?string, rate: ?string, amount: string, note: ?string}, total: string}
     */
    public function quote(Workspace $workspace, Package $package, string $cycle, string $credit = '0.00', bool $lock = false, ?array $price = null): array
    {
        $price    ??= $this->pricing->price($package, $cycle, $workspace);
        $currency   = $price['currency'] ?? (string) $package->price_currency;
        $amount     = $price['amount'] ?? '0.00';
        $adjustment = $this->credits->available($workspace, $currency, bcsub($amount, $credit, 2), $lock);
        $net        = bcsub(bcsub($amount, $credit, 2), $adjustment, 2);
        $tax        = $this->taxes->for($workspace, $net, $this->calendar->today($workspace));

        return ['currency' => $currency, 'amount' => $amount, 'credit' => $credit, 'adjustment' => $adjustment, 'tax' => $tax, 'total' => bcadd($net, $tax['amount'], 2)];
    }

    /**
     * Issues the invoice for $package's $cycle, due by $dueOn - or after the
     * days the admin gives to pay. A period already known (one that starts
     * after a trial, or a renewal's) is printed on it; otherwise the period
     * starts when the payment is confirmed. Its tax is the one in force
     * today, and it keeps it.
     *
     * It takes the business's credit from izSuite off before tax (Doc 15
     * Phase C2b). One the credit covers in full is paid by it at once: the
     * receipt goes out in place of the invoice.
     */
    public function issue(Workspace $workspace, Package $package, string $cycle, ?CarbonInterface $periodStart = null, ?CarbonInterface $dueOn = null, string $kind = SubscriptionInvoice::NEW, string $credit = '0.00', ?array $price = null): SubscriptionInvoice
    {
        $today   = $this->calendar->today($workspace);

        $invoice = DB::transaction(function () use ($workspace, $package, $cycle, $periodStart, $dueOn, $kind, $credit, $today, $price) {
            $quote   = $this->quote($workspace, $package, $cycle, $credit, lock: true, price: $price);
            $invoice = $this->invoices->create($workspace, [
                'number'               => self::INVOICE_PREFIX.str_pad((string) $this->sequences->next('invoice'), 5, '0', STR_PAD_LEFT),
                'kind'                 => $kind,
                'package_id'           => $package->id,
                'package_name'         => $package->name,
                'cycle'                => $cycle,
                'period_starts_on'     => $periodStart?->toDateString(),
                'period_ends_on'       => $periodStart ? $this->periodEnd($periodStart, $cycle)->toDateString() : null,
                'currency'             => $quote['currency'],
                'amount'               => $quote['amount'],
                // An upgrade's credit for the unused part of the paid period (Phase C2b).
                'credit'               => $credit,
                'adjustment'           => $quote['adjustment'],
                'tax_name'             => $quote['tax']['name'],
                'tax_rate'             => $quote['tax']['rate'],
                'tax'                  => $quote['tax']['amount'],
                'tax_note'             => $quote['tax']['note'],
                'total'                => $quote['total'],
                'status'               => SubscriptionInvoice::OPEN,
                'issued_on'            => $today->toDateString(),
                'due_on'               => ($dueOn ?? $today->addDays($this->dueDays()))->toDateString(),
                'billed_to'            => $this->billedTo($workspace),
                'seller'               => $this->seller(),
                'payment_instructions' => $this->instructions(),
            ]);

            $this->credits->take($invoice);

            return $invoice;
        });

        if (bccomp((string) $invoice->total, '0', 2) === 0 && bccomp((string) $invoice->adjustment, '0', 2) > 0) {
            return $this->confirm($invoice, null, ['paid_with' => self::PAID_WITH_CREDIT, 'reference' => null]);
        }

        $this->send($invoice);

        return $invoice;
    }

    /**
     * The payment arrived - izSuite's $admin confirms a transfer, or the card
     * provider reports $payment (Phase D2: paid_with, reference): the invoice
     * is paid, with a receipt number, and the subscription it pays for is
     * active for its period - the one after a trial, else from today - on its
     * plan.
     *
     * A renewal's period follows the one before: paid in grace, the plan
     * carries on from where that one ended; paid before it ends, the daily
     * cycle (SubscriptionCycle) starts it then. An upgrade's starts today, on
     * its plan and cycle, and anything else waiting - a renewal, a move down,
     * a cancellation - gives way to it.
     */
    public function confirm(SubscriptionInvoice $invoice, ?User $admin = null, ?array $payment = null): SubscriptionInvoice
    {
        if (! $invoice->isOpen()) {
            $this->refuse('Only an open invoice can be marked paid.');
        }

        $workspace = $invoice->workspace;
        $start     = $invoice->period_starts_on ?? $this->calendar->today($workspace);
        $end       = $invoice->period_ends_on ?? $this->periodEnd($start, $invoice->cycle);

        DB::transaction(function () use ($invoice, $admin, $payment, $workspace, $start, $end) {
            $this->invoices->update($invoice, [
                'status'            => SubscriptionInvoice::PAID,
                'receipt_number'    => self::RECEIPT_PREFIX.str_pad((string) $this->sequences->next('receipt'), 5, '0', STR_PAD_LEFT),
                'paid_at'           => now(),
                'confirmed_by'      => $admin?->id,
                // Paid by card (Phase D2): the card and the provider's reference, for the receipt.
                'paid_with'         => $payment['paid_with'] ?? null,
                'payment_reference' => $payment['reference'] ?? null,
                'period_starts_on' => $start->toDateString(),
                'period_ends_on'   => $end->toDateString(),
            ]);

            // A transfer izSuite confirms is a payment too (Doc 15 Phase C2a), to refund like a card's; CardPayments keeps a card's.
            if ($payment === null) {
                $this->payments->create([
                    'workspace_id' => $workspace->id, 'subscription_invoice_id' => $invoice->id, 'provider' => SubscriptionPayment::TRANSFER,
                    'reference'    => $invoice->number, 'currency' => $invoice->currency, 'amount' => $invoice->total,
                    'status'       => SubscriptionPayment::SUCCEEDED, 'attempted_on' => $this->calendar->today($workspace)->toDateString(),
                ]);
            }

            $subscription = $this->subscriptions->for($workspace);

            if ($invoice->kind === SubscriptionInvoice::UPGRADE) {
                if ($subscription?->status === WorkspaceSubscription::ACTIVE) {
                    if (($renewal = $this->invoices->openFor($workspace)) && $renewal->kind === SubscriptionInvoice::RENEWAL) {
                        $this->cancelInvoice($renewal);
                    }

                    $this->subscriptions->save($workspace, [
                        'package_id'            => $invoice->package_id, 'cycle' => $invoice->cycle, 'period_starts_on' => $start->toDateString(), 'period_ends_on' => $end->toDateString(),
                        'cancels_at_period_end' => false, 'scheduled_package_id' => null, 'scheduled_cycle' => null, 'last_notice_on' => null,
                        // The new plan's price is the one it pays now (Doc 15 Phase C3a); a new price applied to the old plan goes with it.
                        ...$this->pricePaid($invoice), 'next_price' => null, 'next_price_from' => null,
                    ]);
                    $this->sync->changePackage($workspace, $invoice->package, $admin);
                }

                return;
            }

            if ($invoice->kind === SubscriptionInvoice::RENEWAL) {
                if ($subscription?->status === WorkspaceSubscription::GRACE) {
                    $this->subscriptions->save($workspace, [
                        'status' => WorkspaceSubscription::ACTIVE, 'period_starts_on' => $start->toDateString(), 'period_ends_on' => $end->toDateString(), 'grace_ends_on' => null, 'last_notice_on' => null,
                    ]);
                }

                return;
            }

            if ($subscription?->isCurrent() && $subscription->package_id === $invoice->package_id) {
                $this->subscriptions->save($workspace, ['status' => WorkspaceSubscription::ACTIVE, 'period_starts_on' => $start->toDateString(), 'period_ends_on' => $end->toDateString(), ...$this->pricePaid($invoice)]);

                if ($workspace->package_id !== $invoice->package_id) {
                    $this->sync->changePackage($workspace, $invoice->package, $admin);
                }
            }
        });

        $this->send($invoice->refresh(), receipt: true);

        return $invoice;
    }

    /** An open invoice withdrawn or cancelled: it stays in the history, never to be paid. */
    public function void(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        if (! $invoice->isOpen()) {
            $this->refuse('Only an open invoice can be cancelled.');
        }

        DB::transaction(function () use ($invoice) {
            $this->cancelInvoice($invoice);

            // A subscription still waiting for this payment does not start.
            $subscription = $this->subscriptions->for($invoice->workspace);

            if ($subscription?->status === WorkspaceSubscription::AWAITING_PAYMENT && $subscription->package_id === $invoice->package_id) {
                $this->subscriptions->save($invoice->workspace, ['status' => WorkspaceSubscription::CANCELLED]);
            }
        });

        return $invoice;
    }

    /** @return array{currency: string, price: string} the price a subscription pays from $invoice on: its plan's, before credits and tax (Doc 15 Phase C3a) */
    public function pricePaid(SubscriptionInvoice $invoice): array
    {
        return ['currency' => $invoice->currency, 'price' => (string) $invoice->amount];
    }

    /**
     * An open invoice no longer to be paid, and nothing more: cancelled, and
     * the credit from izSuite it took given back (Doc 15 Phase C2b). void()
     * does this too, for the subscription waiting for it.
     */
    public function cancelInvoice(SubscriptionInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $this->invoices->update($invoice, ['status' => SubscriptionInvoice::VOID, 'voided_at' => now()]);
            $this->credits->giveBack($invoice);
        });
    }

    /** The last day of a period starting on $start: a month or a year on, less a day - 1 Nov to 30 Nov. */
    public function periodEnd(CarbonInterface $start, string $cycle): CarbonInterface
    {
        $start = $start->toImmutable();

        return ($cycle === WorkspaceSubscription::YEARLY ? $start->addYearNoOverflow() : $start->addMonthNoOverflow())->subDay();
    }

    /** izSuite's details on its invoices, as the admin sets them (Settings > Billing). */
    public function seller(): array
    {
        $billing = $this->settings->valuesOf('billing');

        return [
            'name'    => ($billing['billing_company_name'] ?? null) ?: config('brand.name', 'izSuite'),
            'address' => $billing['billing_company_address'] ?? null,
            'email'   => $billing['billing_company_email'] ?? null,
            'tax_id'  => $billing['billing_company_tax_id'] ?? null,
        ];
    }

    private function dueDays(): int
    {
        return max(1, (int) ($this->settings->valuesOf('billing')['billing_invoice_due_days'] ?? self::DUE_DAYS));
    }

    /**
     * The grace days a declined saved card is charged again on (Phase D3),
     * as the admin sets them (Settings > Billing: "1,3") - and the last day
     * of grace, always.
     *
     * @return list<int>
     */
    public function retryDays(): array
    {
        $days = $this->settings->valuesOf('billing')['billing_retry_days'] ?? self::RETRY_DAYS;

        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $days)), fn (int $day) => $day > 0)));
    }

    /** The business's billing details as they are now, its country by name. */
    private function billedTo(Workspace $workspace): array
    {
        $details = $this->billingDetails->for($workspace);

        return [
            'name'     => $details?->name ?? $workspace->name,
            'email'    => $details?->email,
            'address'  => $details?->address,
            'country'  => Countries::name($details?->country) ?? $details?->country,
            'tax_id'   => $details?->tax_id,
            'business' => $workspace->name,
        ];
    }

    /** How to pay: each active offline method's instructions (Bank Transfer), as plain text. */
    private function instructions(): ?string
    {
        $text = $this->paymentMethods->getByType('offline')
            ->where('is_active', true)
            ->map(fn ($method) => trim($method->name."\n".html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)\s*\/?>/i', "\n", (string) $method->instructions)))))
            ->filter()
            ->implode("\n\n");

        return preg_replace("/\n{3,}/", "\n\n", $text) ?: null;
    }

    /**
     * A notice about the subscription (Doc 13 section 19) - its trial ending,
     * a day of its grace, its end - by e-mail to the billing e-mail and the
     * owner. $details is what SubscriptionNoticeMail says.
     */
    public function notice(Workspace $workspace, string $kind, array $details): void
    {
        $to   = $this->recipients($workspace, $this->billingDetails->for($workspace)?->email);
        $mail = new SubscriptionNoticeMail($kind, [...$details, 'business' => $workspace->name]);

        if ($to) {
            Mail::to($to)->queue($mail);
        }

        // In the app too, for those who manage the subscription (Doc 14 section 15) - once a day each.
        $this->tell($workspace, new Notice(
            trigger: "subscription.{$kind}",
            key: "subscription.{$kind}:{$workspace->id}:".$this->calendar->today($workspace)->toDateString(),
            title: $mail->envelope()->subject,
            body: isset($details['invoice']) ? "Invoice {$details['invoice']}, {$details['total']}.".(! empty($details['declined']) ? " {$details['declined']}" : '') : null,
            link: '/settings?tab=subscription',
        ));
    }

    /**
     * A refund confirmed (Doc 15 Phase C2a): by e-mail to the billing e-mail
     * and the owner, and in the app to those who manage the subscription -
     * one notice for each refund.
     */
    public function refunded(SubscriptionRefund $refund): void
    {
        $workspace = $refund->workspace;
        $amount    = $refund->currency.' '.number_format((float) $refund->amount, 2);
        $details   = [
            'plan'     => $refund->invoice?->package_name ?? $workspace->package?->name ?? 'izSuite',
            'business' => $workspace->name,
            'amount'   => $amount,
            'invoice'  => $refund->invoice?->number,
            'method'   => $refund->payment->method(),
        ];
        $mail      = new SubscriptionNoticeMail(SubscriptionNoticeMail::REFUNDED, $details);

        if ($to = $this->recipients($workspace, $refund->invoice?->billed_to['email'] ?? $this->billingDetails->for($workspace)?->email)) {
            Mail::to($to)->queue($mail);
        }

        $this->tell($workspace, new Notice(
            trigger: 'subscription.refunded',
            key: "subscription.refunded:{$refund->id}",
            title: $mail->envelope()->subject,
            body: ($details['invoice'] ? "For invoice {$details['invoice']}, " : '').'back to '.($refund->payment->isTransfer() ? 'your bank account.' : "{$details['method']}."),
            link: '/settings?tab=subscription',
            subject: $refund->invoice,
        ));
    }

    /** The invoice sent again - its receipt, once paid - when izSuite resends it (Doc 15 section 15.13). */
    public function resend(SubscriptionInvoice $invoice): void
    {
        $this->send($invoice, receipt: $invoice->isPaid());
    }

    /** The invoice - or its receipt - by e-mail, to the billing e-mail and the owner; in the app, to those who manage the subscription. */
    private function send(SubscriptionInvoice $invoice, bool $receipt = false): void
    {
        $to   = $this->recipients($invoice->workspace, $invoice->billed_to['email'] ?? null);
        $mail = new SubscriptionBillingMail($invoice, $receipt);

        if ($to) {
            Mail::to($to)->queue($mail);
        }

        $total = $invoice->currency.' '.number_format((float) $invoice->total, 2);

        $this->tell($invoice->workspace, new Notice(
            trigger: $receipt ? 'subscription.paid' : 'subscription.invoice',
            key: ($receipt ? 'subscription.paid:' : 'subscription.invoice:').$invoice->id,
            title: $mail->envelope()->subject,
            body: $receipt ? "{$total} received for invoice {$invoice->number}." : "{$total}, due by {$invoice->due_on->format('j M Y')}.",
            link: '/settings?tab=subscription',
            subject: $invoice,
        ));
    }

    /** $notice in the app for the business's users with Manage Subscription - not the one who did it. */
    private function tell(Workspace $workspace, Notice $notice): void
    {
        $this->notifier->send($workspace, $this->audience->withPermission($workspace, 'settings.subscription'), $notice, auth()->user());
    }

    /** @return list<string> the billing e-mail and the owner's, once each */
    private function recipients(Workspace $workspace, ?string $billingEmail): array
    {
        return collect([$billingEmail, $workspace->user?->email])->filter()->unique()->values()->all();
    }

    /** A 422 for the API, and back to the admin's page with the message there. */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['invoice' => $message]);
    }
}
