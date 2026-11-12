<?php

namespace App\Services\Api\Files;

use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Services\Shared\WorkspaceAccess;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may remove a file from a record past its draft (Doc 14 section 14.12).
 * Once a record is issued, sent, recorded or completed, its files are part of
 * what was settled. Removing one then needs the permission that undoes the
 * record: voiding it, cancelling an offer or reversing a payment. A draft, or
 * a record without a status (a contact or an item), keeps the rule of its
 * route alone.
 */
class FileRemovalRule
{
    /** What removing a settled record's file needs, by the record's class. A sales document's goes by its type. */
    private const NEEDS = [
        Purchase::class        => ['purchases.purchases.void', 'purchase', 'void'],
        Expense::class         => ['expenses.expenses.void', 'expense', 'void'],
        SalesPayment::class    => ['sales.payments.reverse', 'payment', 'reverse'],
        SupplierPayment::class => ['purchases.payments.reverse', 'payment', 'reverse'],
        ExpensePayment::class  => ['expenses.payments.reverse', 'payment', 'reverse'],
    ];

    public function __construct(
        protected WorkspaceAccess $access,
    ) {}

    /**
     * Refuses removing a file from $owner when it is settled and the user may
     * not undo it. Answers its status, for the log, or null for a draft or a
     * record without one.
     */
    public function check(Model $owner): ?string
    {
        $status = $this->settledStatus($owner);

        if ($status === null) {
            return null;
        }

        [$permission, $noun, $verb] = $this->needs($owner);

        abort_unless($this->access->can($permission), 403, "This {$noun} is {$status}. Removing its files needs the permission to {$verb} it.");

        return $status;
    }

    /** Whether the user may remove $owner's files, for the page to offer it. */
    public function allows(Model $owner): bool
    {
        return $this->settledStatus($owner) === null || $this->access->can($this->needs($owner)[0]);
    }

    /** The status of a record past its draft, or null. */
    private function settledStatus(Model $owner): ?string
    {
        if (! $owner instanceof SalesDocument && ! isset(self::NEEDS[$owner::class])) {
            return null;
        }

        $status = $owner->status instanceof BackedEnum ? (string) $owner->status->value : (string) $owner->status;

        return in_array($status, ['', 'draft'], true) ? null : $status;
    }

    /** @return array{string, string, string} the permission, how the message names the record, and what the permission does */
    private function needs(Model $owner): array
    {
        if ($owner instanceof SalesDocument) {
            $verb = $owner->type->isOffer() ? 'cancel' : 'void';

            return [$owner->type->permissionGroup().'.'.$verb, strtolower($owner->type->label()), $verb];
        }

        [$permission, $noun, $verb] = self::NEEDS[$owner::class];

        return [$permission, $owner instanceof SalesPayment && $owner->isRefund() ? 'refund' : $noun, $verb];
    }
}
