<?php

namespace App\Services\Api\Ecommerce;

use App\Jobs\SendStoreCartRecoveryEmail;
use App\Mail\StoreCartRecoveryMail;
use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceCartLine;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceCartRepository;
use App\Services\Documents\DocumentFormat;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An abandoned cart's recovery e-mail (Doc 19 section 19.32 - Phase H3b),
 * sent by hand under Confirm Orders - as a payment link is - once a cart: to
 * its customer, while it is abandoned and they agreed to marketing by e-mail,
 * asked again as it is sent (agreed()). It lists the cart's products with a
 * link back to it - its token's hash kept on the cart, the token carried
 * encrypted by the queue - and is in the Activity Log. Automated campaigns
 * wait for Marketing.
 */
class EcommerceCartRecovery
{
    public function __construct(
        protected EcommerceCartRepository $carts,
        protected WorkspaceAccess $access,
        protected StorefrontCustomerContacts $contacts,
    ) {}

    /** $workspace's abandoned cart $id e-mailed its recovery by $user: the cart, as the page shows it. */
    public function send(Workspace $workspace, User $user, int $id): EcommerceCart
    {
        $cart = $this->carts->findAbandonedFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such cart.');

        if ($cart->recovery_sent_at !== null) {
            throw ValidationException::withMessages(['cart' => 'Its recovery e-mail was sent already: a cart is e-mailed once.']);
        }

        if ($cart->status !== EcommerceCart::ABANDONED) {
            throw ValidationException::withMessages(['cart' => 'Only an abandoned cart is e-mailed: this one is '.[
                EcommerceCart::ACTIVE => 'in use again', EcommerceCart::CONVERTED => 'an order already', EcommerceCart::EXPIRED => 'expired',
            ][$cart->status].'.']);
        }

        if (! $this->agreed($cart)) {
            throw ValidationException::withMessages(['cart' => 'Its customer has not agreed to marketing by e-mail, so it is not e-mailed.']);
        }

        $token = Str::random(48);

        $this->carts->update($cart, ['recovery_token_hash' => hash('sha256', $token), 'recovery_sent_at' => now(), 'recovery_sent_by' => $user->id]);
        SendStoreCartRecoveryEmail::dispatch($cart->id, self::url($cart->storefront, $token))->afterCommit();

        ActivityLogService::record('updated', "Sent a recovery e-mail for the abandoned cart of {$this->who($cart)} on {$cart->storefront->name}", $cart, ['source' => 'api']);

        return $cart;
    }

    /** Sends the cart $cartId's recovery e-mail, with $link - the queue job's work - while the cart is open and its customer known. */
    public function deliver(int $cartId, string $link): void
    {
        $cart = $this->carts->findForRecoveryEmail($cartId);

        if (! $cart || ! $cart->customer_email || ! in_array($cart->status, EcommerceCart::OPEN, true)) {
            return;
        }

        $storefront = $cart->storefront;
        $workspace  = $storefront->workspace;
        $format     = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $money      = fn (?string $amount) => $amount === null ? '' : $format->money($amount, $cart->currency);

        Mail::to($cart->customer_email)->send(new StoreCartRecoveryMail(
            "Your cart at {$storefront->name} is waiting for you",
            $storefront->name,
            $storefront->support_email,
            [
                'name'    => $cart->customer_name,
                'lines'   => $cart->lines->map(fn (EcommerceCartLine $line) => [
                    'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'),
                    'title'    => $line->product?->title ?? 'A product no longer listed',
                    'amount'   => $money($line->unit_price === null ? null : bcmul((string) $line->unit_price, (string) $line->quantity, 2)),
                ])->all(),
                'total'   => $money((string) $cart->grand_total),
                'link'    => $link,
                'account' => $cart->storefront_customer_id ? rtrim((string) config('app.frontend_url'), '/').'/store/'.$storefront->code.'/account' : null,
            ],
        ));
    }

    /** The link back to a cart: its store's cart page, opening the cart in the browser by $token. */
    public static function url(Storefront $storefront, string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/store/'.$storefront->code.'/cart?recover='.$token;
    }

    /**
     * Whether $cart's customer agreed to marketing by e-mail, by their latest
     * word: a guest's box ticked at checkout; a signed-in customer's account
     * consent given, or the box ticked - unless they refused or withdrew it
     * from their account since that checkout.
     */
    private function agreed(EcommerceCart $cart): bool
    {
        if (! $cart->account) {
            return (bool) $cart->marketing;
        }

        if ($this->contacts->marketingOf($cart->storefront, $cart->account) === true) {
            return true;
        }

        return (bool) $cart->marketing && ! $this->contacts->refusedSince($cart->storefront, $cart->account, $cart->last_activity_at ?? $cart->updated_at);
    }

    /** How the Activity Log names $cart's customer: their name, with their e-mail. */
    private function who(EcommerceCart $cart): string
    {
        return $cart->customer_name ? "{$cart->customer_name} ({$cart->customer_email})" : (string) $cart->customer_email;
    }
}
