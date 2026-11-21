<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommercePaymentMethod;
use App\Models\User\Workspace;
use App\Repositories\User\EcommercePaymentMethodRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Validation\ValidationException;

/**
 * Settings → eCommerce's payment methods (Doc 19 sections 19.22 and 19.37 -
 * Phase D2), under Manage Payment Methods: added after the last, changed and
 * deleted, each change logged.
 *
 * A method takes money into an active account of the business that takes
 * customer payments - or the one it has already. A bank transfer or mobile
 * money says how to pay. Cash on delivery is for shipped orders and asks no
 * reference; paying on pickup the same for picked-up ones. Orders placed by a
 * method (Phase D4) keep what it was once it is changed or deleted.
 */
class EcommercePaymentMethodService
{
    /** The types whose customers must be told where to pay. */
    private const NEED_INSTRUCTIONS = [EcommercePaymentMethod::BANK_TRANSFER, EcommercePaymentMethod::MOBILE_MONEY];

    public function __construct(
        protected EcommercePaymentMethodRepository $methods,
        protected PaymentAccountRepository $accounts,
    ) {}

    /**
     * The methods in their order, and the accounts a method may take money into.
     *
     * @return array{methods: \Illuminate\Database\Eloquent\Collection, accounts: \Illuminate\Database\Eloquent\Collection}
     */
    public function show(Workspace $workspace): array
    {
        return [
            'methods'  => $this->methods->methods($workspace),
            'accounts' => $this->accounts->takingCustomerPayments($workspace),
        ];
    }

    public function add(Workspace $workspace, array $data): EcommercePaymentMethod
    {
        $method = $this->methods->create($workspace, [...$this->checked($workspace, $data), 'position' => $this->methods->nextPosition($workspace)]);

        ActivityLogService::record('created', "Added the online payment method \"{$method->name}\"", $method, ['source' => 'api']);

        return $method;
    }

    public function update(Workspace $workspace, int $id, array $data): EcommercePaymentMethod
    {
        $method = $this->find($workspace, $id);

        $this->methods->update($method, $this->checked($workspace, $data, $method));

        ActivityLogService::record('updated', "Updated the online payment method \"{$method->name}\"", $method, ['source' => 'api', 'changes' => SettingChanges::of($method)]);

        return $method;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $method = $this->find($workspace, $id);

        $this->methods->delete($method);

        ActivityLogService::record('deleted', "Deleted the online payment method \"{$method->name}\"", $method, ['source' => 'api']);
    }

    private function find(Workspace $workspace, int $id): EcommercePaymentMethod
    {
        return $this->methods->find($workspace, $id) ?? abort(404, 'There is no such payment method.');
    }

    /**
     * $data as a method keeps it: its name its own, its account one it may
     * take money into, its instructions where they are needed, its reference
     * and delivery as its type allows, its countries once each - none for
     * every one - and its amounts in order.
     */
    private function checked(Workspace $workspace, array $data, ?EcommercePaymentMethod $method = null): array
    {
        $name = trim($data['name']);
        $type = $data['type'];

        if ($this->methods->nameTaken($workspace, $name, $method?->id)) {
            throw ValidationException::withMessages(['name' => 'Another payment method has this name.']);
        }

        $accountId = (int) $data['payment_account_id'];

        if ($accountId !== $method?->payment_account_id && ! $this->accounts->takingCustomerPayments($workspace)->contains('id', $accountId)) {
            throw ValidationException::withMessages(['payment_account_id' => 'Choose an active payment account that takes customer payments.']);
        }

        $instructions = trim((string) ($data['instructions'] ?? ''));

        if ($instructions === '' && in_array($type, self::NEED_INSTRUCTIONS, true)) {
            throw ValidationException::withMessages(['instructions' => 'Tell customers how to pay: the account or number to pay into.']);
        }

        if (isset($data['min_order'], $data['max_order']) && bccomp((string) $data['min_order'], (string) $data['max_order'], 2) > 0) {
            throw ValidationException::withMessages(['max_order' => 'The highest order amount is below the lowest.']);
        }

        $countries = array_values(array_unique($data['countries'] ?? []));

        return [
            'name'               => $name,
            'type'               => $type,
            'payment_account_id' => $accountId,
            'instructions'       => $instructions === '' ? null : $instructions,
            'reference'          => in_array($type, self::NEED_INSTRUCTIONS, true) ? ($data['reference'] ?? 'optional') : 'none',
            'countries'          => $countries === [] ? null : $countries,
            'min_order'          => $data['min_order'] ?? null,
            'max_order'          => $data['max_order'] ?? null,
            'delivery'           => match ($type) {
                EcommercePaymentMethod::CASH_ON_DELIVERY => EcommercePaymentMethod::SHIPPED,
                EcommercePaymentMethod::PAY_ON_PICKUP    => EcommercePaymentMethod::PICKUP,
                default                                  => $data['delivery'] ?? EcommercePaymentMethod::ANY,
            },
            'is_active'          => (bool) ($data['is_active'] ?? true),
        ];
    }
}
