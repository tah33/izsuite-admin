<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerCode;
use App\Models\User\StorefrontCustomerToken;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Repositories\User\StorefrontCustomerRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A storefront customer's account and sign-in (Doc 19 section 19.16 - Phase
 * E1), on an open store, apart from the business's users:
 *
 * - signing up: a new account of the business - or a closed one with the
 *   e-mail opened again - its e-mail sent a code to verify it. While
 *   Settings → eCommerce asks for verified e-mails, it is signed in only
 *   once verified; else at once;
 * - verifying the e-mail by its code, which signs it in - and, from Phase
 *   E2, links the account to its contact, its e-mail's guest orders its own;
 * - signing in - five tries a minute for an e-mail from one address - and
 *   out;
 * - a forgotten password: a code e-mailed, then a new password with it -
 *   which verifies the e-mail too, and signs every other sign-in out.
 *
 * A sign-in is a token of its own: its hash is kept, for 60 days from its
 * last use (StorefrontCustomerToken). What is said to an unknown e-mail
 * never tells whether it has an account, but for signing up with one that
 * has.
 */
class StorefrontCustomerAuth
{
    public const TOKEN_DAYS = 60;

    private const WRONG_SIGN_IN = 'The e-mail or the password is not right.';

    public function __construct(
        protected StorefrontCatalogService $stores,
        protected StorefrontCatalogRepository $catalog,
        protected StorefrontCustomerRepository $customers,
        protected EcommerceSettingRepository $settings,
        protected StorefrontCustomerCodes $codes,
        protected StorefrontCustomerContacts $contacts,
    ) {}

    /**
     * $data: name, email, password and phone. The account, and - while the
     * business does not wait for verified e-mails - its sign-in's token.
     *
     * @return array{customer: StorefrontCustomer, token: ?string}
     */
    public function signUp(string $code, array $data): array
    {
        $storefront = $this->openStore($code);
        $email      = mb_strtolower(trim($data['email']));
        $existing   = $this->customers->findByEmail($storefront->workspace, $email);

        if ($existing?->status === StorefrontCustomer::ACTIVE) {
            throw ValidationException::withMessages(['email' => 'An account uses this e-mail already: sign in, or reset its password.']);
        }

        $fields = [
            'name' => trim($data['name']), 'phone' => trim((string) ($data['phone'] ?? '')) ?: null, 'password' => $data['password'],
            'status' => StorefrontCustomer::ACTIVE, 'closed_at' => null, 'email_verified_at' => null,
        ];

        // Closed before, it opens again - to be verified again: its contact and orders wait for that (Phase E2).
        if ($existing) {
            $this->contacts->unlinkAccount($existing);
        }

        $customer = $existing
            ? $this->customers->update($existing, [...$fields, 'storefront_id' => $storefront->id])
            : $this->customers->create($storefront, [...$fields, 'email' => $email]);

        ActivityLogService::recordCustomer($storefront->workspace, $existing ? 'updated' : 'created', "{$customer->name} ".($existing ? 'opened their account again' : 'signed up').' on '.$storefront->name, $customer);

        $this->codes->issue($storefront, $customer, StorefrontCustomerCode::VERIFY);

        return ['customer' => $customer, 'token' => $this->waitsForVerifying($storefront) ? null : $this->signIn($customer)];
    }

    /** The e-mail verified by its code - and signed in. */
    public function verify(string $code, string $email, string $digits): array
    {
        $storefront = $this->openStore($code);
        $customer   = $this->activeAccount($storefront, $email);

        if ($customer?->isVerified()) {
            throw ValidationException::withMessages(['code' => 'This e-mail is verified already: sign in.']);
        }

        $this->codes->check($customer, StorefrontCustomerCode::VERIFY, $digits);
        $this->customers->update($customer, ['email_verified_at' => now()]);
        $this->contacts->linkAccount($storefront, $customer);

        return ['customer' => $customer, 'token' => $this->signIn($customer)];
    }

    /** A new code to verify $email - sent only to an account waiting for one. */
    public function resend(string $code, string $email): void
    {
        $storefront = $this->openStore($code);
        $customer   = $this->activeAccount($storefront, $email);

        if ($customer && ! $customer->isVerified()) {
            $this->codes->ensureCooledDown($customer, StorefrontCustomerCode::VERIFY);
            $this->codes->issue($storefront, $customer, StorefrontCustomerCode::VERIFY);
        }
    }

    /** Signed in with $email and $password, from $ip - an unverified account only while the business does not wait for verifying. */
    public function signInWith(string $code, string $email, string $password, string $ip): array
    {
        $storefront = $this->openStore($code);
        $key        = 'store-sign-in:'.$storefront->workspace_id.':'.mb_strtolower(trim($email)).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new HttpResponseException(response()->json(['message' => 'Too many tries: try again in '.RateLimiter::availableIn($key).' seconds.'], 429));
        }

        $customer = $this->activeAccount($storefront, $email);

        if (! $customer || ! Hash::check($password, $customer->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => self::WRONG_SIGN_IN]);
        }

        RateLimiter::clear($key);

        if (! $customer->isVerified() && $this->waitsForVerifying($storefront)) {
            if (! $this->codes->cooldown($customer, StorefrontCustomerCode::VERIFY)) {
                $this->codes->issue($storefront, $customer, StorefrontCustomerCode::VERIFY);
            }

            throw new HttpResponseException(response()->json(['message' => 'Verify your e-mail first: we have sent you a code.', 'verify' => true], 403));
        }

        return ['customer' => $customer, 'token' => $this->signIn($customer)];
    }

    public function signOut(StorefrontCustomerToken $token): void
    {
        ActivityLogService::skipForThisRequest();
        $this->customers->deleteToken($token);
    }

    /** A code to choose a new password - sent only to an account with $email. */
    public function forgot(string $code, string $email): void
    {
        $storefront = $this->openStore($code);
        $customer   = $this->activeAccount($storefront, $email);

        if ($customer && ! $this->codes->cooldown($customer, StorefrontCustomerCode::RESET)) {
            $this->codes->issue($storefront, $customer, StorefrontCustomerCode::RESET);
        }
    }

    /** A new password by its code - the e-mail verified with it, every other sign-in signed out - and signed in. */
    public function reset(string $code, string $email, string $digits, string $password): array
    {
        $storefront = $this->openStore($code);
        $customer   = $this->activeAccount($storefront, $email);

        $this->codes->check($customer, StorefrontCustomerCode::RESET, $digits);
        $this->customers->update($customer, ['password' => $password, 'email_verified_at' => $customer->email_verified_at ?? now()]);
        $this->customers->deleteTokens($customer);
        $this->contacts->linkAccount($storefront, $customer);

        return ['customer' => $customer, 'token' => $this->signIn($customer)];
    }

    /**
     * The sign-in $token at the store at $code: kept on while it is used,
     * its account active and the store's business's - else none. The store
     * need not be open: its customer still reaches their account.
     */
    public function fromToken(string $code, string $token): ?StorefrontCustomerToken
    {
        $storefront = $this->catalog->findStore($code);
        $signIn     = $storefront ? $this->customers->findToken(hash('sha256', $token)) : null;

        if (! $signIn || $signIn->customer?->status !== StorefrontCustomer::ACTIVE || (int) $signIn->customer->workspace_id !== (int) $storefront->workspace_id) {
            return null;
        }

        if ($signIn->last_used_at === null || $signIn->last_used_at->lt(now()->subHour())) {
            $this->customers->touchToken($signIn, now()->addDays(self::TOKEN_DAYS));
        }

        $signIn->customer->setRelation('storefront', $storefront);

        return $signIn;
    }

    /** A new sign-in for $customer: its token, given once. */
    public function signIn(StorefrontCustomer $customer): string
    {
        $token = Str::random(64);

        $this->customers->addToken($customer, hash('sha256', $token), now()->addDays(self::TOKEN_DAYS));
        $this->customers->update($customer, ['last_signed_in_at' => now()]);

        return $token;
    }

    private function openStore(string $code): Storefront
    {
        ActivityLogService::skipForThisRequest();

        return $this->stores->openStore($code)['storefront'];
    }

    private function activeAccount(Storefront $storefront, string $email): ?StorefrontCustomer
    {
        $customer = $this->customers->findByEmail($storefront->workspace, mb_strtolower(trim($email)));

        return $customer?->status === StorefrontCustomer::ACTIVE ? $customer : null;
    }

    private function waitsForVerifying(Storefront $storefront): bool
    {
        return (bool) $this->settings->forWorkspace($storefront->workspace)->verify_customer_email;
    }
}
