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
use App\Support\CashDenominations;
use App\Support\SettingChanges;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Settings → Point of Sale (Doc 18 sections 18.4, 18.11, 18.15 and 18.32 -
 * Phase A1): the walk-in customer, the receipt's return policy and footer,
 * and the payment methods. The first time it is opened it starts with a
 * Cash method, on the business's cash account that takes customer payments.
 * A payment method takes money into an active account of the business that
 * takes customer payments, and gives change only when it is cash.
 *
 * Closing sessions (Phases B2a and B2b), saved apart: the variance limit,
 * whether cash is counted by notes and coins, and each of the business's
 * currencies' notes and coins - one it has not set starts with izSuite's
 * usual ones (CashDenominations). Each change is logged.
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
     * The settings, each of the business's currencies' notes and coins, the
     * payment methods in their order, and the accounts a method may take
     * money into.
     *
     * @return array{settings: PosSetting, denominations: list<array{currency: string, values: list<string>}>, methods: \Illuminate\Database\Eloquent\Collection, accounts: \Illuminate\Database\Eloquent\Collection}
     */
    public function show(Workspace $workspace): array
    {
        $this->start($workspace);

        $settings = $this->settings->forWorkspace($workspace);

        return [
            'settings'      => $settings,
            'denominations' => array_map(fn (string $currency) => ['currency' => $currency, 'values' => $this->denominationsOf($settings, $currency)], $workspace->currencyCodes()),
            'methods'       => $this->settings->methods($workspace),
            'accounts'      => $this->accounts->takingCustomerPayments($workspace),
        ];
    }

    /**
     * $data is validated (see SavePosCashCountRequest): closing sessions -
     * the variance limit, whether cash is counted by notes and coins, and the
     * notes and coins of the currencies given, each the business's.
     */
    public function saveCashCount(Workspace $workspace, array $data): array
    {
        $this->start($workspace);

        $lists = $this->settings->forWorkspace($workspace)->denominations ?? [];

        foreach (array_values($data['denominations'] ?? []) as $index => $row) {
            if (! in_array($row['currency'], $workspace->currencyCodes(), true)) {
                throw ValidationException::withMessages(["denominations.{$index}.currency" => "Choose one of the business's currencies."]);
            }

            $lists[$row['currency']] = CashDenominations::sorted($row['values']);
        }

        $settings = $this->settings->save($workspace, [
            'variance_limit'       => ($data['variance_limit'] ?? null) === null ? null : bcadd((string) $data['variance_limit'], '0', 2),
            // Phase F1: kept as it was when not given.
            ...(array_key_exists('cash_movement_limit', $data) ? ['cash_movement_limit' => $data['cash_movement_limit'] === null ? null : bcadd((string) $data['cash_movement_limit'], '0', 2)] : []),
            'counts_denominations' => (bool) $data['counts_denominations'],
            'denominations'        => $lists === [] ? null : $lists,
        ]);

        ActivityLogService::record('updated', 'Updated the POS cash count settings', $workspace, ['source' => 'api', 'changes' => SettingChanges::of($settings)]);

        return $this->show($workspace);
    }

    /**
     * $data is validated (see SavePosSellingSettingsRequest): selling (Phase
     * C3) - the discount limit, a percentage, and the step a cash sale's
     * total rounds to - each empty for none.
     */
    public function saveSelling(Workspace $workspace, array $data): array
    {
        $this->start($workspace);

        $settings = $this->settings->save($workspace, [
            'discount_limit' => ($data['discount_limit'] ?? null) === null ? null : bcadd((string) $data['discount_limit'], '0', 2),
            'cash_rounding'  => ($data['cash_rounding'] ?? null) === null ? null : bcadd((string) $data['cash_rounding'], '0', 2),
            // Phase D1: how long a held order is kept - for as long as it takes, when null.
            'hold_hours'     => isset($data['hold_hours']) ? (int) $data['hold_hours'] : null,
            // Phase I1: after how many minutes without use a register locks - only by its cashier, when null.
            'lock_minutes'   => isset($data['lock_minutes']) ? (int) $data['lock_minutes'] : null,
        ]);

        ActivityLogService::record('updated', 'Updated the POS selling settings', $workspace, ['source' => 'api', 'changes' => SettingChanges::of($settings)]);

        return $this->show($workspace);
    }

    /**
     * The notes and coins a session in $currency is counted by - null when the
     * business counts its cash as one amount, or the currency has none.
     */
    public function countingFor(Workspace $workspace, string $currency): ?array
    {
        $settings = $this->settings->forWorkspace($workspace);
        $values   = $settings->counts_denominations ? $this->denominationsOf($settings, $currency) : [];

        return $values === [] ? null : $values;
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

    /** The currency's notes and coins as the business set them, else izSuite's usual ones. */
    private function denominationsOf(PosSetting $settings, string $currency): array
    {
        $lists = $settings->denominations ?? [];

        return array_key_exists($currency, $lists) ? $lists[$currency] : CashDenominations::usual($currency);
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

        if ($cash && ! empty($data['confirms_later'])) {
            throw ValidationException::withMessages(['confirms_later' => 'Cash is in the drawer at once: it does not wait to be confirmed.']);
        }

        return [
            'name'               => $name,
            'type'               => $data['type'],
            'payment_account_id' => $accountId,
            'needs_reference'    => (bool) ($data['needs_reference'] ?? false),
            'gives_change'       => $cash && (bool) ($data['gives_change'] ?? false),
            'confirms_later'     => ! $cash && (bool) ($data['confirms_later'] ?? false),
            'is_active'          => (bool) ($data['is_active'] ?? true),
        ];
    }
}
