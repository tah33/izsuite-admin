<?php

namespace App\Services\Api\Ecommerce;

use App\Mail\StoreCustomerCodeMail;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerCode;
use App\Repositories\User\StorefrontCustomerRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * The codes e-mailed to a storefront customer (Doc 19 Phase E1) - to verify
 * their e-mail, or to reset their password - as izSuite's own are: six
 * digits, kept as a hash, holding for 30 minutes, a new one a minute after
 * the last. A code is spent once used, after five wrong tries, or when a new
 * one is sent.
 *
 * Checking says the same for any wrong code - no code, a spent one, wrong
 * digits - and only someone who typed the right digits learns that the code
 * has expired, so it never tells which e-mails have accounts.
 */
class StorefrontCustomerCodes
{
    public const LENGTH = 6;

    public const MINUTES = 30;

    public const COOLDOWN_SECONDS = 60;

    public const WRONG = 'That code is not right. Check the e-mail and try again.';

    public const EXPIRED = 'That code has expired. Ask for a new one.';

    public function __construct(
        protected StorefrontCustomerRepository $customers,
    ) {}

    /**
     * A new code for $purpose, e-mailed from $storefront. A mail server away
     * is reported, not thrown: the customer can ask for another.
     */
    public function issue(Storefront $storefront, StorefrontCustomer $customer, string $purpose): void
    {
        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        $this->customers->addCode($customer, $purpose, Hash::make($code), now()->addMinutes(self::MINUTES));

        try {
            Mail::to($customer->email)->send(new StoreCustomerCodeMail($storefront->name, $storefront->support_email, $purpose, $code, self::MINUTES));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The seconds left before another code for $purpose may be sent - none when it may. */
    public function cooldown(StorefrontCustomer $customer, string $purpose): int
    {
        $last = $this->customers->openCode($customer, $purpose);

        return $last ? max(0, self::COOLDOWN_SECONDS - (int) $last->created_at->diffInSeconds(now())) : 0;
    }

    /** Refuses with a 429 while a new code for $purpose must wait. */
    public function ensureCooledDown(StorefrontCustomer $customer, string $purpose): void
    {
        if ($wait = $this->cooldown($customer, $purpose)) {
            throw new HttpResponseException(response()->json(['message' => "Ask for a new code in {$wait} seconds."], 429));
        }
    }

    /**
     * $digits as $customer's code for $purpose: spent once right; refused
     * under $field when wrong or spent, and - only when right - expired.
     */
    public function check(?StorefrontCustomer $customer, string $purpose, string $digits, string $field = 'code'): void
    {
        $code = $customer ? $this->customers->openCode($customer, $purpose) : null;

        if (! $code || $code->attempts >= StorefrontCustomerCode::MAX_ATTEMPTS || ! Hash::check($digits, $code->code_hash)) {
            if ($code) {
                $this->customers->updateCode($code, ['attempts' => $code->attempts + 1]);
            }

            throw ValidationException::withMessages([$field => self::WRONG]);
        }

        if ($code->expires_at->isPast()) {
            throw ValidationException::withMessages([$field => self::EXPIRED]);
        }

        $this->customers->updateCode($code, ['used_at' => now()]);
    }
}
