<?php

namespace App\Services\Api\Pos;

use App\Enums\Edition;
use App\Models\User\PosPaymentMethod;
use App\Models\User\PosRegister;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PosRegisterRepository;
use App\Repositories\User\PosSettingRepository;
use App\Repositories\User\PriceListRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * POS registers (Doc 18 section 18.5 - Phase A2): the Registers page - those
 * at the locations the user may use - what a register's form chooses from,
 * and adding, changing and deleting one.
 *
 * A register belongs to one active location of the business, sells in one of
 * its currencies, and takes at least one payment method switched on whose
 * money goes into an active account in that currency - a cash method's into
 * the register's own cash account, when it has one. Its price list (the full
 * Sales app's) is in its currency and for its location; its cash account
 * belongs to its location or to the whole business; its cashiers are active
 * members who may use its location. Its receipt numbers start with its code
 * unless given another prefix. Each change is logged.
 */
class PosRegisterService
{
    public function __construct(
        protected PosRegisterRepository $registers,
        protected PosSettingRepository $settings,
        protected PaymentAccountRepository $accounts,
        protected PriceListRepository $priceLists,
        protected LocationRepository $locations,
        protected WorkspaceMemberRepository $members,
        protected WorkspaceAccess $access,
        protected PosSettingService $posSettings,
    ) {}

    /** The registers at the locations the user may use, by name. */
    public function index(Workspace $workspace): Collection
    {
        return $this->registers->getFor($workspace, $this->access->locations());
    }

    /**
     * What a register's form chooses from: the business's currencies, its
     * price lists (with full Sales), the accounts taking customer payments,
     * the payment methods switched on, and who can be a cashier where.
     */
    public function options(Workspace $workspace): array
    {
        // Settings → Point of Sale may not have been opened yet: its Cash method starts here too.
        $this->posSettings->start($workspace);

        return [
            'currencies'      => $workspace->currencyCodes(),
            'price_lists'     => $this->sellsWithPriceLists($workspace) ? $this->priceLists->activeFor($workspace) : new Collection,
            'accounts'        => $this->accounts->takingCustomerPayments($workspace),
            'payment_methods' => $this->settings->methods($workspace)->where('is_active', true)->values(),
            'cashiers'        => $this->people($workspace),
        ];
    }

    /** $data is validated (see SavePosRegisterRequest). */
    public function create(Workspace $workspace, array $data): PosRegister
    {
        [$fields, $methodIds, $cashierIds] = $this->checked($workspace, $data);

        $register = DB::transaction(fn () => $this->registers->create($workspace, $fields, $methodIds, $cashierIds));

        ActivityLogService::record('created', 'Added the POS register '.$this->quoted($register), $register, ['source' => 'api']);

        return $register;
    }

    public function update(Workspace $workspace, int $id, array $data): PosRegister
    {
        $register = $this->find($workspace, $id);
        [$fields, $methodIds, $cashierIds] = $this->checked($workspace, $data, $register);

        DB::transaction(fn () => $this->registers->update($register, $fields, $methodIds, $cashierIds));

        ActivityLogService::record('updated', 'Updated the POS register '.$this->quoted($register), $register, ['source' => 'api', 'changes' => SettingChanges::of($register)]);

        return $register;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $register = $this->find($workspace, $id);
        $this->registers->delete($register);

        ActivityLogService::record('deleted', 'Deleted the POS register '.$this->quoted($register), $register, ['source' => 'api']);
    }

    private function find(Workspace $workspace, int $id): PosRegister
    {
        return $this->registers->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'Register not found.');
    }

    /**
     * $data as a register keeps it, with the ids of the methods it takes and
     * of its cashiers - each checked as the class says.
     *
     * @return array{0: array, 1: list<int>, 2: list<int>}
     */
    private function checked(Workspace $workspace, array $data, ?PosRegister $register = null): array
    {
        $name = trim($data['name']);
        $code = Str::upper(trim($data['code']));

        foreach (['name' => $name, 'code' => $code] as $column => $value) {
            if ($this->registers->taken($workspace, $column, $value, $register?->id)) {
                throw ValidationException::withMessages([$column => "Another register has this {$column}."]);
            }
        }

        $locationId = (int) $data['location_id'];
        $location   = $locationId === $register?->location_id ? null : $this->locations->findFor($workspace, $locationId);

        if ($locationId !== $register?->location_id && (! $location || ! $location->is_active || ! $this->access->usesLocation($locationId))) {
            throw ValidationException::withMessages(['location_id' => 'Choose one of your locations that is switched on.']);
        }

        $currency = $data['currency'];

        if (! in_array($currency, $workspace->currencyCodes(), true)) {
            throw ValidationException::withMessages(['currency' => "Choose one of the business's currencies."]);
        }

        $priceListId = ($data['price_list_id'] ?? null) ?: null;

        if ($priceListId !== null) {
            $list = $this->sellsWithPriceLists($workspace) ? $this->priceLists->activeFor($workspace)->firstWhere('id', (int) $priceListId) : null;

            if (! $list || $list->currency !== $currency || (! $list->all_locations && ! $list->locations->contains('id', $locationId))) {
                throw ValidationException::withMessages(['price_list_id' => "Choose an active price list in the register's currency, for its location."]);
            }
        }

        $accounts      = $this->accounts->takingCustomerPayments($workspace)->keyBy('id');
        $cashAccountId = ($data['cash_account_id'] ?? null) ?: null;
        $cashAccount   = $cashAccountId === null ? null : $accounts->get((int) $cashAccountId);

        if ($cashAccountId !== null && (! $cashAccount || $cashAccount->currency !== $currency || ($cashAccount->location_id !== null && $cashAccount->location_id !== $locationId))) {
            throw ValidationException::withMessages(['cash_account_id' => "Choose an active account taking customer payments, in the register's currency, of its location or the whole business."]);
        }

        $methodIds = array_values(array_unique(array_map('intval', $data['payment_method_ids'])));
        $methods   = $this->settings->methods($workspace)->keyBy('id');

        foreach ($methodIds as $methodId) {
            $method = $methods->get($methodId);
            // A cash method's money goes into the register's own cash account, when it has one.
            $into = $method?->type === PosPaymentMethod::CASH && $cashAccount ? $cashAccount : $accounts->get((int) $method?->payment_account_id);

            if (! $method || ! $method->is_active || ! $into || $into->currency !== $currency) {
                throw ValidationException::withMessages(['payment_method_ids' => 'Choose payment methods switched on whose accounts take customer payments in the register\'s currency'.($method ? ': "'.$method->name.'" does not.' : '.')]);
            }
        }

        $cashierIds = array_values(array_unique(array_map('intval', $data['cashier_ids'] ?? [])));
        $people     = collect($this->people($workspace))->keyBy('id');

        foreach ($cashierIds as $cashierId) {
            $person = $people->get($cashierId);

            if (! $person || ($person['locations'] !== null && ! in_array($locationId, $person['locations'], true))) {
                throw ValidationException::withMessages(['cashier_ids' => 'Choose active members who may use the register\'s location'.($person ? ': '.$person['name'].' may not.' : '.')]);
            }
        }

        $prefix = trim((string) ($data['receipt_prefix'] ?? ''));

        return [[
            'name'            => $name,
            'code'            => $code,
            'location_id'     => $locationId,
            'currency'        => $currency,
            'price_list_id'   => $priceListId === null ? null : (int) $priceListId,
            'cash_account_id' => $cashAccount?->id,
            'receipt_prefix'  => $prefix === '' ? $code.'-' : $prefix,
            'status'          => $data['status'] ?? PosRegister::ACTIVE,
        ], $methodIds, $cashierIds];
    }

    /**
     * Who can be a cashier: the business's active members and its owner, by
     * name, each with the locations they may use - null for every one.
     *
     * @return list<array{id: int, name: string, locations: list<int>|null}>
     */
    private function people(Workspace $workspace): array
    {
        $people = $this->members->getFor($workspace)
            ->filter(fn (WorkspaceMember $member) => $member->status === WorkspaceMember::ACTIVE && $member->user)
            ->map(fn (WorkspaceMember $member) => ['id' => (int) $member->user_id, 'name' => $member->user->name, 'locations' => $this->access->locationsOf($member->user, $workspace)]);

        if ($workspace->user && ! $people->contains('id', (int) $workspace->user_id)) {
            $people->push(['id' => (int) $workspace->user_id, 'name' => $workspace->user->name, 'locations' => null]);
        }

        return $people->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** Price lists are the full Sales app's. */
    private function sellsWithPriceLists(Workspace $workspace): bool
    {
        return WorkspaceApps::for($workspace)->has('sales', Edition::Full);
    }

    /** '"Front Counter" (R1)'. */
    private function quoted(PosRegister $register): string
    {
        return '"'.Str::limit($register->name, 100).'" ('.$register->code.')';
    }
}
