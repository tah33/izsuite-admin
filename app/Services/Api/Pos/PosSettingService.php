<?php

namespace App\Services\Api\Pos;

use App\Models\User\Contact;
use App\Models\User\PosPaymentMethod;
use App\Models\User\PosSetting;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PosRegisterRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Settings → Point of Sale (Doc 18 sections 18.4, 18.11, 18.15 and 18.32 -
 * Phase A1): the walk-in customer, the receipt's return policy and footer,
 * and the payment methods. The first time it is opened it starts with a
 * Cash method, on the business's cash account that takes customer payments.
 * A payment method takes money into an active account of the business that
 * takes customer payments, and gives change only when it is cash. Each
 * change is logged.
 */
class PosSettingService
{
    use RefusesConflicts;

    /** What the settings form saves. */
    private const SAVED = ['walk_in_contact_id', 'return_policy', 'receipt_footer'];

    public function __construct(
        protected PosSettingRepository $settings,
        protected PaymentAccountRepository $accounts,
        protected ContactRepository $contacts,
        protected PosRegisterRepository $registers,
    ) {}

    /**
     * The settings, the payment methods in their order, and the accounts a
     * method may take money into.
     *
     * @return array{settings: PosSetting, methods: \Illuminate\Database\Eloquent\Collection, accounts: \Illuminate\Database\Eloquent\Collection}
     */
    public function show(Workspace $workspace): array
    {
        $this->start($workspace);

        return [
            'settings' => $this->settings->forWorkspace($workspace),
            'methods'  => $this->settings->methods($workspace),
            'accounts' => $this->accounts->takingCustomerPayments($workspace),
        ];
    }

    /** The first time: the settings made, and a Cash method on the business's cash account, if it has one. */
    public function start(Workspace $workspace): void
    {
        if ($this->settings->started($workspace)) {
            return;
        }

        $this->settings->save($workspace, []);
        $cash = $this->accounts->startingCash($workspace);

        if ($cash) {
            $this->settings->createMethod($workspace, ['name' => 'Cash', 'type' => PosPaymentMethod::CASH, 'payment_account_id' => $cash->id, 'gives_change' => true, 'position' => 1]);
        }
    }

    /** $data is validated (see SavePosSettingsRequest): the walk-in customer, one a sale can be made to, or none. */
    public function save(Workspace $workspace, array $data): array
    {
        $this->start($workspace);

        $fields = Arr::only($data, self::SAVED);
        $before = $this->settings->forWorkspace($workspace);

        if (! empty($fields['walk_in_contact_id']) && (int) $fields['walk_in_contact_id'] !== $before->walk_in_contact_id) {
            $contact = $this->contacts->findFor($workspace, (int) $fields['walk_in_contact_id']);

            if (! $contact || ! $contact->is_active || $contact->isMerged() || ! in_array($contact->role, [Contact::ROLE_CUSTOMER, Contact::ROLE_BOTH], true)) {
                throw ValidationException::withMessages(['walk_in_contact_id' => 'Choose one of your active customers.']);
            }
        }

        $settings = $this->settings->save($workspace, [...$fields, 'walk_in_contact_id' => ($fields['walk_in_contact_id'] ?? null) ?: null]);

        ActivityLogService::record('updated', 'Updated the POS settings', $workspace, ['source' => 'api', 'changes' => SettingChanges::of($settings)]);

        return $this->show($workspace);
    }

    /** $data is validated (see SavePosPaymentMethodRequest); it goes last. */
    public function addMethod(Workspace $workspace, array $data): PosPaymentMethod
    {
        $this->start($workspace);

        $method = $this->settings->createMethod($workspace, [...$this->checked($workspace, $data), 'position' => $this->settings->nextMethodPosition($workspace)]);

        ActivityLogService::record('created', "Added the POS payment method \"{$method->name}\"", $method, ['source' => 'api']);

        return $method;
    }

    public function updateMethod(Workspace $workspace, int $id, array $data): PosPaymentMethod
    {
        $method = $this->findMethod($workspace, $id);
        $this->settings->updateMethod($method, $this->checked($workspace, $data, $method));

        ActivityLogService::record('updated', "Updated the POS payment method \"{$method->name}\"", $method, ['source' => 'api', 'changes' => SettingChanges::of($method)]);

        return $method;
    }

    public function deleteMethod(Workspace $workspace, int $id): void
    {
        $method = $this->findMethod($workspace, $id);
        // Phase A2: registers take it.
        $registers = $this->registers->takingMethod($method);

        if ($registers->isNotEmpty()) {
            $this->refuse('Registers take this method ('.$registers->pluck('name')->implode(', ').'). Take it off them, or switch it off instead.');
        }
        $this->settings->deleteMethod($method);

        ActivityLogService::record('deleted', "Deleted the POS payment method \"{$method->name}\"", $method, ['source' => 'api']);
    }

    private function findMethod(Workspace $workspace, int $id): PosPaymentMethod
    {
        return $this->settings->findMethod($workspace, $id) ?? abort(404, 'There is no such payment method.');
    }

    /**
     * $data as a method keeps it: its name its own, its account the
     * business's, active and taking customer payments - or the method's own
     * still - and change given only in cash.
     */
    private function checked(Workspace $workspace, array $data, ?PosPaymentMethod $method = null): array
    {
        $name = trim($data['name']);

        if ($this->settings->methodNameTaken($workspace, $name, $method?->id)) {
            throw ValidationException::withMessages(['name' => 'Another payment method has this name.']);
        }

        $accountId = (int) $data['payment_account_id'];

        if ($accountId !== $method?->payment_account_id && ! $this->accounts->takingCustomerPayments($workspace)->contains('id', $accountId)) {
            throw ValidationException::withMessages(['payment_account_id' => 'Choose an active payment account that takes customer payments.']);
        }

        $cash = $data['type'] === PosPaymentMethod::CASH;

        if (! $cash && ! empty($data['gives_change'])) {
            throw ValidationException::withMessages(['gives_change' => 'Change is given only in cash.']);
        }

        return [
            'name'               => $name,
            'type'               => $data['type'],
            'payment_account_id' => $accountId,
            'needs_reference'    => (bool) ($data['needs_reference'] ?? false),
            'gives_change'       => $cash && (bool) ($data['gives_change'] ?? false),
            'is_active'          => (bool) ($data['is_active'] ?? true),
        ];
    }
}
