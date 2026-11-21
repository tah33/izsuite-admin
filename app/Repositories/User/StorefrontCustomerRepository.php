<?php

namespace App\Repositories\User;

use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerAddress;
use App\Models\User\StorefrontCustomerCode;
use App\Models\User\StorefrontCustomerToken;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Storefront customers' accounts (Doc 19 Phase E1): an account by its
 * e-mail in a business, its sign-ins by their tokens' hashes, and the codes
 * e-mailed to it - and, from Phase E2, its saved addresses.
 */
class StorefrontCustomerRepository
{
    /** $workspace's account with $email - in small letters - closed or not. */
    public function findByEmail(Workspace $workspace, string $email): ?StorefrontCustomer
    {
        return StorefrontCustomer::query()->where('workspace_id', $workspace->id)->where('email', $email)->first();
    }

    /** Made through the storefront it signs up on, so its business is never read from $data. */
    public function create(Storefront $storefront, array $data): StorefrontCustomer
    {
        $customer = new StorefrontCustomer(['storefront_id' => $storefront->id, ...$data]);
        $customer->workspace_id = $storefront->workspace_id;
        $customer->save();

        return $customer;
    }

    public function update(StorefrontCustomer $customer, array $data): StorefrontCustomer
    {
        $customer->update($data);

        return $customer;
    }

    public function addToken(StorefrontCustomer $customer, string $tokenHash, Carbon $expiresAt): StorefrontCustomerToken
    {
        return $customer->tokens()->create(['token_hash' => $tokenHash, 'last_used_at' => now(), 'expires_at' => $expiresAt]);
    }

    /** The sign-in with $tokenHash while it has not expired, with its account. */
    public function findToken(string $tokenHash): ?StorefrontCustomerToken
    {
        return StorefrontCustomerToken::query()->where('token_hash', $tokenHash)->where('expires_at', '>', now())->with('customer')->first();
    }

    /** $token used now: kept until $expiresAt. */
    public function touchToken(StorefrontCustomerToken $token, Carbon $expiresAt): void
    {
        $token->update(['last_used_at' => now(), 'expires_at' => $expiresAt]);
    }

    public function deleteToken(StorefrontCustomerToken $token): void
    {
        $token->delete();
    }

    /** All of $customer's sign-ins - but the one $keepId, when given. */
    public function deleteTokens(StorefrontCustomer $customer, ?int $keepId = null): void
    {
        $customer->tokens()->when($keepId, fn ($query, int $id) => $query->whereKeyNot($id))->delete();
    }

    /** $customer's latest code for $purpose not used yet. */
    public function openCode(StorefrontCustomer $customer, string $purpose): ?StorefrontCustomerCode
    {
        return $customer->codes()->where('purpose', $purpose)->whereNull('used_at')->latest('id')->first();
    }

    /** A new code for $purpose, any earlier one for it spent. */
    public function addCode(StorefrontCustomer $customer, string $purpose, string $codeHash, Carbon $expiresAt): StorefrontCustomerCode
    {
        $customer->codes()->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

        return $customer->codes()->create(['purpose' => $purpose, 'code_hash' => $codeHash, 'expires_at' => $expiresAt]);
    }

    public function updateCode(StorefrontCustomerCode $code, array $data): void
    {
        $code->update($data);
    }

    /** Phase E2: $customer's saved addresses, the defaults first, then the newest. */
    public function addresses(StorefrontCustomer $customer): Collection
    {
        return $customer->addresses()->orderByDesc('is_default_billing')->orderByDesc('is_default_shipping')->latest('id')->get();
    }

    public function findAddress(StorefrontCustomer $customer, int $id): ?StorefrontCustomerAddress
    {
        return $customer->addresses()->whereKey($id)->first();
    }

    public function addAddress(StorefrontCustomer $customer, array $data): StorefrontCustomerAddress
    {
        return $customer->addresses()->create($data);
    }

    public function updateAddress(StorefrontCustomerAddress $address, array $data): StorefrontCustomerAddress
    {
        $address->update($data);

        return $address;
    }

    public function deleteAddress(StorefrontCustomerAddress $address): void
    {
        $address->delete();
    }

    /** $customer's addresses but $keepId no longer the default for $kind - billing or shipping. */
    public function clearDefault(StorefrontCustomer $customer, string $kind, int $keepId): void
    {
        $customer->addresses()->whereKeyNot($keepId)->where("is_default_{$kind}", true)->update(["is_default_{$kind}" => false]);
    }

    /** Closing the account: its saved addresses go. */
    public function deleteAddresses(StorefrontCustomer $customer): void
    {
        $customer->addresses()->delete();
    }
}
