<?php

namespace App\Services\Shared;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Http\Request;

/**
 * The actions a record offers (Doc 12 section 13), less those the signed-in
 * user's role does not allow - so a page shows only the buttons that work.
 * The API refuses the rest on its own (EnsurePermission); this only keeps
 * them off the page.
 *
 * Each action needs one permission, or - a list - all of them: an invoice's
 * refund is a credit note and a refund at once.
 */
class AllowedActions
{
    public function __construct(
        protected WorkspaceAccess $access,
    ) {}

    /**
     * A sales document's actions, by its kind. On Sales Lite a sales order -
     * the full Sales app's (Doc 16 Phase B) - offers none, as it is only read,
     * and nothing converts to one.
     */
    public function sales(DocumentType $type, array $actions): array
    {
        $kind = $type->permissionGroup();

        if (! $this->fullSales()) {
            $actions = $type->edition() === Edition::Full ? [] : array_values(array_diff($actions, ['convert_to_sales_order']));
        }

        return $this->keep($actions, [
            'edit'                   => "{$kind}.edit",
            'accept'                 => "{$kind}.edit",
            'reject'                 => "{$kind}.edit",
            'issue'                  => "{$kind}.issue",
            'send'                   => "{$kind}.send",
            'cancel'                 => "{$kind}.cancel",
            'void'                   => "{$kind}.void",
            'delete'                 => "{$kind}.delete",
            'confirm'                => "{$kind}.confirm",
            'hold'                   => "{$kind}.confirm",
            'resume'                 => "{$kind}.confirm",
            'complete'               => "{$kind}.confirm",
            'amend'                  => "{$kind}.amend",
            'duplicate'              => "{$kind}.create",
            'convert_to_proforma'    => 'sales.proformas.create',
            'convert_to_sales_order' => 'sales.orders.create',
            'convert_to_invoice'     => 'sales.invoices.create',
            'create_proforma'        => 'sales.proformas.create',
            'create_invoice'         => 'sales.invoices.create',
            'record_payment'         => 'sales.payments.record',
            'credit_note'            => 'sales.credit_notes.create',
            'refund'                 => $type === DocumentType::Invoice ? ['sales.credit_notes.create', 'sales.refunds.record'] : 'sales.refunds.record',
        ]);
    }

    /** How a sales document reaches its customer - e-mail, reminder, a link: its kind's Send or Share - none for an order on Sales Lite. */
    public function delivery(DocumentType $type, array $ways): array
    {
        if ($type->edition() === Edition::Full && ! $this->fullSales()) {
            return [];
        }

        return $this->access->can("{$type->permissionGroup()}.send") ? $ways : [];
    }

    /** Whether the business the request is for has the full Sales app on. */
    private function fullSales(): bool
    {
        $workspace = app(Request::class)->attributes->get('workspace');

        return $workspace instanceof Workspace && WorkspaceApps::for($workspace)->has('sales', Edition::Full);
    }

    /** A purchase's or an expense's actions - $group "purchases.purchases" or "expenses.expenses". */
    public function record(string $group, array $actions): array
    {
        $family = strtok($group, '.');

        return $this->keep($actions, [
            'edit'           => "{$group}.edit",
            'record'         => "{$group}.record",
            'void'           => "{$group}.void",
            'delete'         => "{$group}.delete",
            'record_payment' => "{$family}.payments.record",
        ]);
    }

    /** A payment's reversal, and a receipt's e-mail - $family "sales", "purchases" or "expenses". */
    public function payment(string $family, array $actions): array
    {
        return $this->keep($actions, ['reverse' => "{$family}.payments.reverse", 'email' => "{$family}.payments.record"]);
    }

    /** A transfer between accounts: Transfer Money. */
    public function transfer(array $actions): array
    {
        return $this->access->can('payment_accounts.transfer') ? $actions : [];
    }

    /**
     * @param  array<string, string|list<string>>  $needs  an action not here needs nothing
     * @return list<string>
     */
    private function keep(array $actions, array $needs): array
    {
        return array_values(array_filter($actions, fn (string $action) => collect((array) ($needs[$action] ?? []))
            ->every(fn (string $permission) => $this->access->can($permission))));
    }
}
