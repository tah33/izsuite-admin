<?php

namespace App\Services\Billing\Payments;

use App\Models\Billing\PaymentMethod;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\WorkspaceCard;
use App\Models\User\Workspace;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Support\MinorUnits;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Stripe (Doc 13 Phase D2), through its REST API: a customer per business,
 * Checkout's own page to pay an invoice - keeping the card for payments made
 * later without the business there - or to save a card, the checkout read
 * back once finished, and webhooks taken only with Stripe's signature. Its
 * keys are the Stripe payment method's (Admin > Payment Methods), of the
 * mode it is set to: test or live.
 */
class StripeGateway implements CardGateway
{
    private const API       = 'https://api.stripe.com/v1/';

    /** Seconds a webhook's signature stays good. */
    private const TOLERANCE = 300;

    /** The events of a finished checkout; others are acknowledged and left. */
    private const FINISHED  = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public function __construct(
        private PaymentMethodRepository $methods,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function available(): bool
    {
        return (bool) $this->method()?->is_active && filled($this->keys()['secret_key'] ?? null);
    }

    public function live(): bool
    {
        return ! ($this->method()?->is_sandbox ?? true);
    }

    public function customer(Workspace $workspace, string $name, ?string $email): string
    {
        return $this->post('customers', array_filter(['name' => $name, 'email' => $email, 'metadata' => ['workspace_id' => $workspace->id]]))['id'];
    }

    public function checkout(SubscriptionInvoice $invoice, string $customerId, string $returnUrl): string
    {
        $metadata = ['workspace_id' => $invoice->workspace_id, 'invoice_id' => $invoice->id];

        return $this->post('checkout/sessions', [
            'mode'                 => 'payment',
            'customer'             => $customerId,
            'client_reference_id'  => (string) $invoice->id,
            'payment_method_types' => ['card'],
            'line_items'           => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower($invoice->currency),
                    'unit_amount'  => MinorUnits::of((string) $invoice->total, $invoice->currency),
                    'product_data' => ['name' => "izSuite {$invoice->package_name} plan, {$invoice->cycle} ({$invoice->number})"],
                ],
            ]],
            // The card stays on the customer, for payments made later without the business there (Phase D3).
            'payment_intent_data'  => ['setup_future_usage' => 'off_session', 'metadata' => $metadata],
            'metadata'             => $metadata,
            'success_url'          => $this->back($returnUrl),
            'cancel_url'           => $returnUrl,
            'expires_at'           => now()->addHour()->timestamp,
        ])['url'];
    }

    public function cardSetup(Workspace $workspace, string $customerId, string $returnUrl): string
    {
        return $this->post('checkout/sessions', [
            'mode'                 => 'setup',
            'customer'             => $customerId,
            'payment_method_types' => ['card'],
            'metadata'             => ['workspace_id' => $workspace->id],
            'success_url'          => $this->back($returnUrl),
            'cancel_url'           => $returnUrl,
        ])['url'];
    }

    public function finished(string $sessionId): ?CheckoutOutcome
    {
        $session = $this->get('checkout/sessions/'.rawurlencode($sessionId), ['expand' => ['payment_intent.payment_method', 'setup_intent.payment_method']]);
        $payment = ($session['mode'] ?? null) === 'payment';
        $invoice = (int) ($session['metadata']['invoice_id'] ?? 0);

        if (($session['status'] ?? null) !== 'complete' || empty($session['metadata']['workspace_id'])
            || ($payment && (($session['payment_status'] ?? null) !== 'paid' || ! $invoice))) {
            return null;
        }

        $intent  = $session[$payment ? 'payment_intent' : 'setup_intent'] ?? null;
        $method  = is_array($intent) ? ($intent['payment_method'] ?? null) : null;

        return new CheckoutOutcome(
            workspaceId: (int) $session['metadata']['workspace_id'],
            invoiceId: $payment ? $invoice : null,
            customerId: (string) (is_array($session['customer'] ?? null) ? $session['customer']['id'] : ($session['customer'] ?? '')),
            livemode: (bool) ($session['livemode'] ?? false),
            card: is_array($method) ? self::card($method) : null,
            paymentRef: $payment && is_array($intent) ? ($intent['id'] ?? null) : null,
            amountMinor: $payment ? (int) (is_array($intent) ? ($intent['amount_received'] ?? 0) : ($session['amount_total'] ?? 0)) : null,
            currency: $payment ? strtoupper((string) ($session['currency'] ?? '')) : null,
        );
    }

    public function charge(SubscriptionInvoice $invoice, WorkspaceCard $card, string $key): ChargeResult
    {
        $response = $this->request(fn (PendingRequest $http) => $http->asForm()->withHeaders(['Idempotency-Key' => $key])->post(self::API.'payment_intents', [
            'amount'         => MinorUnits::of((string) $invoice->total, $invoice->currency),
            'currency'       => strtolower($invoice->currency),
            'customer'       => $card->customer_id,
            'payment_method' => $card->card_id,
            'off_session'    => 'true',
            'confirm'        => 'true',
            'description'    => "izSuite {$invoice->package_name} plan, {$invoice->cycle} ({$invoice->number})",
            'metadata'       => ['workspace_id' => $invoice->workspace_id, 'invoice_id' => $invoice->id],
        ]));

        // The bank refusing - a decline, or the cardholder to confirm it - is Stripe's card error.
        if ($response->status() === 402) {
            $error  = $response->json('error') ?? [];
            $intent = $error['payment_intent'] ?? [];
            $action = ($error['code'] ?? null) === 'authentication_required' || ($intent['status'] ?? null) === 'requires_action';

            return new ChargeResult($action ? ChargeResult::ACTION_REQUIRED : ChargeResult::FAILED, $intent['id'] ?? null, $error['message'] ?? 'The card was declined.');
        }

        $intent   = $this->ensure($response);

        return match ($intent['status'] ?? null) {
            'succeeded'       => new ChargeResult(ChargeResult::SUCCEEDED, $intent['id'], null, (int) ($intent['amount_received'] ?? 0), strtoupper((string) ($intent['currency'] ?? ''))),
            'processing'      => new ChargeResult(ChargeResult::PROCESSING, $intent['id']),
            'requires_action' => new ChargeResult(ChargeResult::ACTION_REQUIRED, $intent['id'], 'The bank asks the cardholder to confirm this payment.'),
            default           => new ChargeResult(ChargeResult::FAILED, $intent['id'] ?? null, 'The card was not charged.'),
        };
    }

    public function notified(string $payload, ?string $signature): ?CheckoutOutcome
    {
        $secret = $this->keys()['webhook_secret'] ?? null;

        if (! $secret || ! $signature || ! $this->signed($payload, $signature, $secret)) {
            throw new InvalidNotification('This notification is not signed by Stripe.');
        }

        $event  = json_decode($payload, true);
        $id     = $event['data']['object']['id'] ?? null;

        return match (true) {
            ! $id                                                => null,
            in_array($event['type'] ?? null, self::FINISHED, true) => $this->finished($id),
            ($event['type'] ?? null) === 'payment_intent.succeeded' => $this->paid($id),
            default                                              => null,
        };
    }

    /** A payment for an invoice that succeeded - charged without the business there, or on Stripe's page - as a finished checkout. */
    private function paid(string $intentId): ?CheckoutOutcome
    {
        $intent  = $this->get('payment_intents/'.rawurlencode($intentId), ['expand' => ['payment_method']]);
        $invoice = (int) ($intent['metadata']['invoice_id'] ?? 0);

        if (($intent['status'] ?? null) !== 'succeeded' || ! $invoice || empty($intent['metadata']['workspace_id'])) {
            return null;
        }

        return new CheckoutOutcome(
            workspaceId: (int) $intent['metadata']['workspace_id'],
            invoiceId: $invoice,
            customerId: (string) ($intent['customer'] ?? ''),
            livemode: (bool) ($intent['livemode'] ?? false),
            card: is_array($intent['payment_method'] ?? null) ? self::card($intent['payment_method']) : null,
            paymentRef: $intent['id'],
            amountMinor: (int) ($intent['amount_received'] ?? 0),
            currency: strtoupper((string) ($intent['currency'] ?? '')),
        );
    }

    public function removeCard(string $cardId): void
    {
        $this->post('payment_methods/'.rawurlencode($cardId).'/detach', []);
    }

    /** Whether Stripe's "t=...,v1=..." header signs $payload with $secret, within the last few minutes. */
    private function signed(string $payload, string $header, string $secret): bool
    {
        $time       = 0;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $time = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $time || abs(now()->timestamp - $time) > self::TOLERANCE) {
            return false;
        }

        $expected   = hash_hmac('sha256', "{$time}.{$payload}", $secret);

        return collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature));
    }

    /** Where Stripe sends the business back once finished, naming the checkout. */
    private function back(string $returnUrl): string
    {
        return $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'card_checkout={CHECKOUT_SESSION_ID}';
    }

    /** @return array{id: string, brand: ?string, last4: ?string, exp_month: ?int, exp_year: ?int} */
    private static function card(array $method): array
    {
        $card = $method['card'] ?? [];

        return [
            'id'        => (string) $method['id'],
            'brand'     => $card['brand'] ?? null,
            'last4'     => $card['last4'] ?? null,
            'exp_month' => isset($card['exp_month']) ? (int) $card['exp_month'] : null,
            'exp_year'  => isset($card['exp_year']) ? (int) $card['exp_year'] : null,
        ];
    }

    /** The Stripe method as the admin has it now - read each time, so a change is never missed by a long-lived instance. */
    private function method(): ?PaymentMethod
    {
        return $this->methods->findOnlineBySlug('stripe');
    }

    private function keys(): array
    {
        return $this->method()?->keys() ?? [];
    }

    private function post(string $path, array $params): array
    {
        return $this->send(fn (PendingRequest $http) => $http->asForm()->post(self::API.$path, $params));
    }

    private function get(string $path, array $query): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get(self::API.$path, $query));
    }

    private function send(callable $call): array
    {
        return $this->ensure($this->request($call));
    }

    private function request(callable $call): Response
    {
        try {
            return $call(Http::withToken((string) ($this->keys()['secret_key'] ?? ''))->acceptJson()->timeout(20));
        } catch (ConnectionException $e) {
            throw new CardPaymentError('Stripe could not be reached: '.$e->getMessage(), 0, $e);
        }
    }

    /** Stripe's answer, refused as CardPaymentError when it failed. */
    private function ensure(Response $response): array
    {
        if ($response->failed()) {
            throw new CardPaymentError('Stripe refused: '.($response->json('error.message') ?? 'status '.$response->status()), $response->status());
        }

        return $response->json() ?? [];
    }
}
