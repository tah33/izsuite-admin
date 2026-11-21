<?php

namespace App\Services\Api\Ecommerce;

use App\Models\Admin\Language;
use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerToken;
use App\Repositories\Admin\LanguageRepository;
use App\Repositories\User\StorefrontCustomerRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A signed-in storefront customer's own account (Doc 19 section 19.16 -
 * Phase E1): its profile - name and phone - a new password, which signs its
 * other sign-ins out, and closing it with its password: kept, closed, with
 * its orders (Phase E2) and every sign-in signed out. Signing up again with
 * its e-mail opens it again (StorefrontCustomerAuth).
 *
 * Phase E3: its language, one of the store's - its contact's too - and,
 * once its e-mail is verified, its marketing by e-mail, given or withdrawn,
 * as its contact's consent (StorefrontCustomerContacts).
 */
class StorefrontCustomerAccount
{
    public function __construct(
        protected StorefrontCustomerRepository $customers,
        protected StorefrontCustomerContacts $contacts,
        protected LanguageRepository $languages,
    ) {}

    /**
     * $customer's account as its page shows it, on the store it is signed in
     * at: whether its contact takes marketing by e-mail (null until its e-mail
     * is verified), and the store's languages to choose from.
     *
     * @return array{customer: StorefrontCustomer, marketing: ?bool, languages: list<array{code: string, name: string}>}
     */
    public function view(StorefrontCustomer $customer): array
    {
        $codes = $this->storeLanguages($customer);
        $names = $this->languages->getActive()->whereIn('code', $codes)->mapWithKeys(fn (Language $language) => [$language->code => $language->name]);

        return [
            'customer'  => $customer,
            'marketing' => $this->contacts->marketingOf($customer->storefront, $customer),
            'languages' => array_map(fn (string $code) => ['code' => $code, 'name' => $names[$code] ?? strtoupper($code)], $codes),
        ];
    }

    /**
     * $data: name and phone; from Phase E3 its language, and - its e-mail
     * verified - whether it takes marketing by e-mail. The account as view()
     * gives it.
     */
    public function update(StorefrontCustomer $customer, array $data): array
    {
        ActivityLogService::skipForThisRequest();
        $language = array_key_exists('language', $data) ? ($data['language'] ?: null) : $customer->language;

        if ($language !== null && ! in_array($language, $this->storeLanguages($customer), true)) {
            throw ValidationException::withMessages(['language' => 'Choose one of the languages of '.$customer->storefront->name.'.']);
        }

        // All of it or none: marketing refused (an e-mail not verified) keeps the rest as it was.
        DB::transaction(function () use ($customer, $data, $language) {
            $this->customers->update($customer, ['name' => trim($data['name']), 'phone' => trim((string) ($data['phone'] ?? '')) ?: null, 'language' => $language]);

            if ($language !== null) {
                $this->contacts->languageChosen($customer, $language);
            }

            if (($data['marketing'] ?? null) !== null) {
                $this->contacts->setMarketing($customer->storefront, $customer, (bool) $data['marketing']);
            }
        });

        return $this->view($customer);
    }

    /** A new password, given the current one - every sign-in but $token signed out. */
    public function changePassword(StorefrontCustomerToken $token, string $current, string $password): void
    {
        ActivityLogService::skipForThisRequest();
        $customer = $token->customer;

        if (! Hash::check($current, $customer->password)) {
            throw ValidationException::withMessages(['current_password' => 'This is not your current password.']);
        }

        $this->customers->update($customer, ['password' => $password]);
        $this->customers->deleteTokens($customer, $token->id);
    }

    /** Closed, given its password: kept with its orders, every sign-in signed out, its saved addresses deleted (Phase E2). */
    public function close(StorefrontCustomer $customer, string $password): void
    {
        if (! Hash::check($password, $customer->password)) {
            throw ValidationException::withMessages(['password' => 'This is not your password.']);
        }

        $this->customers->update($customer, ['status' => StorefrontCustomer::CLOSED, 'closed_at' => now()]);
        $this->customers->deleteTokens($customer);
        $this->customers->deleteAddresses($customer);

        ActivityLogService::recordCustomer($customer->workspace, 'updated', "{$customer->name} closed their account", $customer);
    }

    /** The languages of the store $customer is signed in at, its default first. */
    private function storeLanguages(StorefrontCustomer $customer): array
    {
        $storefront = $customer->storefront;

        return array_values(array_unique(array_filter([$storefront->default_language, ...($storefront->languages ?? [])])));
    }
}
