<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerAddress;
use App\Repositories\User\StorefrontCustomerRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A storefront customer's saved addresses (Doc 19 section 19.16 - Phase E2),
 * up to StorefrontCustomerAddress::MAX: added, changed and deleted on the
 * account's page, and kept from each order placed signed in - one the account
 * has already is not kept twice. One is the default for billing and one -
 * the same or another - for delivery: the first kept is both, and making
 * another the default takes it from the one before. Checkout starts from
 * them. None of this is the business's activity: none is logged.
 */
class StorefrontCustomerAddresses
{
    public function __construct(
        protected StorefrontCustomerRepository $customers,
    ) {}

    public function list(StorefrontCustomer $customer): Collection
    {
        return $this->customers->addresses($customer);
    }

    /** $data: the address's fields, and whether it is the default for billing and for delivery. */
    public function add(StorefrontCustomer $customer, array $data): StorefrontCustomerAddress
    {
        ActivityLogService::skipForThisRequest();
        $kept = $this->customers->addresses($customer);

        if ($kept->count() >= StorefrontCustomerAddress::MAX) {
            throw ValidationException::withMessages(['line1' => 'You can keep '.StorefrontCustomerAddress::MAX.' addresses: delete one first.']);
        }

        $address = $this->customers->addAddress($customer, [
            ...$this->fields($data),
            'is_default_billing'  => (bool) ($data['default_billing'] ?? false) || ! $kept->contains('is_default_billing', true),
            'is_default_shipping' => (bool) ($data['default_shipping'] ?? false) || ! $kept->contains('is_default_shipping', true),
        ]);

        return $this->defaultsKept($customer, $address);
    }

    /** A default stays one until another is made it: unticking it alone changes nothing. */
    public function update(StorefrontCustomer $customer, int $id, array $data): StorefrontCustomerAddress
    {
        ActivityLogService::skipForThisRequest();
        $address = $this->find($customer, $id);

        $this->customers->updateAddress($address, [
            ...$this->fields($data),
            'is_default_billing'  => $address->is_default_billing || (bool) ($data['default_billing'] ?? false),
            'is_default_shipping' => $address->is_default_shipping || (bool) ($data['default_shipping'] ?? false),
        ]);

        return $this->defaultsKept($customer, $address);
    }

    /** Deleted: a default it was passes to the newest address left. */
    public function delete(StorefrontCustomer $customer, int $id): void
    {
        ActivityLogService::skipForThisRequest();
        $address = $this->find($customer, $id);
        $this->customers->deleteAddress($address);

        $left = $this->customers->addresses($customer)->sortByDesc('id');

        foreach (['billing', 'shipping'] as $kind) {
            if ($address->{"is_default_{$kind}"} && ($next = $left->first())) {
                $this->customers->updateAddress($next, ["is_default_{$kind}" => true]);
            }
        }
    }

    /**
     * An order placed signed in: its billing address, and the one it is
     * shipped to (the same, or none when it is not shipped), each kept unless
     * the account has it - while there is room. The account's first address
     * for billing, and the first it is shipped to, become its defaults.
     */
    public function keepFromOrder(StorefrontCustomer $customer, array $billing, ?array $shipping): void
    {
        foreach (array_filter(['billing' => $billing, 'shipping' => $shipping]) as $kind => $given) {
            $kept  = $this->customers->addresses($customer);
            $match = $kept->first(fn (StorefrontCustomerAddress $address) => $this->same($address->only(StorefrontCustomerAddress::FIELDS), $given));

            if ($match === null && $kept->count() >= StorefrontCustomerAddress::MAX) {
                continue;
            }

            $match ??= $this->customers->addAddress($customer, $this->fields($given));

            if (! $kept->contains("is_default_{$kind}", true)) {
                $this->customers->updateAddress($match, ["is_default_{$kind}" => true]);
            }
        }
    }

    private function find(StorefrontCustomer $customer, int $id): StorefrontCustomerAddress
    {
        return $this->customers->findAddress($customer, $id) ?? abort(404, 'There is no such address.');
    }

    /** Defaults taken from the account's other addresses where $address is now one. */
    private function defaultsKept(StorefrontCustomer $customer, StorefrontCustomerAddress $address): StorefrontCustomerAddress
    {
        foreach (['billing', 'shipping'] as $kind) {
            if ($address->{"is_default_{$kind}"}) {
                $this->customers->clearDefault($customer, $kind, $address->id);
            }
        }

        return $address;
    }

    /** An address's fields as an account keeps them: trimmed, empty ones none, the country in capitals. */
    private function fields(array $data): array
    {
        $fields = [];

        foreach (StorefrontCustomerAddress::FIELDS as $field) {
            $fields[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        }

        $fields['country'] = strtoupper((string) $fields['country']);

        return $fields;
    }

    /** Whether two addresses are one: every field alike, whatever its case and spaces. */
    private function same(array $kept, array $given): bool
    {
        $read = fn (array $address) => array_map(fn (string $field) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) ($address[$field] ?? '')))), StorefrontCustomerAddress::FIELDS);

        return $read($kept) === $read($given);
    }
}
