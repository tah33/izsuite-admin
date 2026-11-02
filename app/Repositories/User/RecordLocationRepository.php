<?php

namespace App\Repositories\User;

use App\Models\User\AccountTransfer;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\PaymentAccount;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Support\RouteRecords;

/**
 * Where a business's records are (Doc 12 section 21), by the kinds of
 * RouteRecords: what LocationAccess checks a member's locations against.
 */
class RecordLocationRepository
{
    private const MODELS = [
        RouteRecords::SALES_DOCUMENT   => SalesDocument::class,
        RouteRecords::SALES_PAYMENT    => SalesPayment::class,
        RouteRecords::PURCHASE         => Purchase::class,
        RouteRecords::SUPPLIER_PAYMENT => SupplierPayment::class,
        RouteRecords::EXPENSE          => Expense::class,
        RouteRecords::EXPENSE_PAYMENT  => ExpensePayment::class,
        RouteRecords::PAYMENT_ACCOUNT  => PaymentAccount::class,
    ];

    /**
     * The location record $id of $kind is at - for a transfer, its two
     * accounts' - with null for none; null when the business has no such
     * record.
     *
     * @return list<int|null>|null
     */
    public function locationsOf(Workspace $workspace, string $kind, int $id): ?array
    {
        if ($kind === RouteRecords::ACCOUNT_TRANSFER) {
            $transfer = AccountTransfer::query()
                ->where('workspace_id', $workspace->id)
                ->with(['fromAccount:id,location_id', 'toAccount:id,location_id'])
                ->find($id, ['id', 'from_account_id', 'to_account_id']);

            return $transfer ? [$transfer->fromAccount?->location_id, $transfer->toAccount?->location_id] : null;
        }

        $record = (self::MODELS[$kind])::query()->where('workspace_id', $workspace->id)->find($id, ['id', 'location_id']);

        return $record ? [$record->location_id] : null;
    }
}
