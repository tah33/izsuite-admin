<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Models\User\Contact;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Item;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Global Search's queries (Doc 14 section 16): the first few records of one
 * kind in one business that a term finds. Contacts by name, phone or e-mail,
 * items by name, SKU or barcode, documents by number or reference, payments
 * by number or reference. Contacts and items come by name, active ones
 * first; transactions come newest first, kept to $locations (null for every
 * location, see AtLocation).
 */
class GlobalSearchRepository
{
    private const CONTACT_COLUMNS = ['name', 'trading_name', 'contact_person', 'email', 'contact_number', 'phone', 'alt_phone'];

    public function contacts(Workspace $workspace, string $term, int $limit): Collection
    {
        return Contact::query()
            ->where('workspace_id', $workspace->id)
            ->where(function (Builder $query) use ($term) {
                $this->anyLike($query, self::CONTACT_COLUMNS, $term);
                $this->samePhone($query, ['phone', 'alt_phone'], $term);
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'trading_name', 'email', 'phone', 'is_active', 'created_at']);
    }

    public function items(Workspace $workspace, string $term, int $limit): Collection
    {
        return Item::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn (Builder $query) => $this->anyLike($query, ['name', 'sku', 'barcode', 'item_number'], $term))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'barcode', 'is_active', 'created_at']);
    }

    /**
     * @param  list<DocumentType>  $types  the sales kinds to look in
     * @param  list<int>|null  $locations
     */
    public function salesDocuments(Workspace $workspace, string $term, array $types, ?array $locations, int $limit): Collection
    {
        return $this->newest(SalesDocument::query()
            ->whereIn('type', array_map(fn (DocumentType $type) => $type->value, $types)), $workspace, $term, ['number', 'customer_reference', 'sales_reference'], $locations, $limit, 'document_date');
    }

    /** @param  list<int>|null  $locations */
    public function purchases(Workspace $workspace, string $term, ?array $locations, int $limit): Collection
    {
        return $this->newest(Purchase::query(), $workspace, $term, ['number', 'reference', 'supplier_invoice_number'], $locations, $limit, 'document_date');
    }

    /** @param  list<int>|null  $locations */
    public function expenses(Workspace $workspace, string $term, ?array $locations, int $limit): Collection
    {
        return $this->newest(Expense::query(), $workspace, $term, ['number', 'reference', 'receipt_number'], $locations, $limit, 'document_date');
    }

    /** Customer payments and refunds. @param  list<int>|null  $locations */
    public function salesPayments(Workspace $workspace, string $term, ?array $locations, int $limit): Collection
    {
        return $this->newest(SalesPayment::query(), $workspace, $term, ['number', 'reference'], $locations, $limit, 'payment_date');
    }

    /** @param  list<int>|null  $locations */
    public function supplierPayments(Workspace $workspace, string $term, ?array $locations, int $limit): Collection
    {
        return $this->newest(SupplierPayment::query(), $workspace, $term, ['number', 'reference'], $locations, $limit, 'payment_date');
    }

    /** @param  list<int>|null  $locations */
    public function expensePayments(Workspace $workspace, string $term, ?array $locations, int $limit): Collection
    {
        return $this->newest(ExpensePayment::query()->with('expense'), $workspace, $term, ['number', 'reference'], $locations, $limit, 'payment_date');
    }

    /**
     * $query's records in $workspace whose $columns hold $term, at $locations,
     * newest by $dated first. Each comes with its contact's name and its
     * location's.
     *
     * @param  list<string>  $columns
     * @param  list<int>|null  $locations
     */
    private function newest(Builder $query, Workspace $workspace, string $term, array $columns, ?array $locations, int $limit, string $dated): Collection
    {
        return $query
            ->where('workspace_id', $workspace->id)
            ->where(fn (Builder $query) => $this->anyLike($query, $columns, $term))
            ->atLocations($locations)
            ->with(['contact:id,name', 'location:id,name'])
            ->orderByDesc($dated)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @param  list<string>  $columns */
    private function anyLike(Builder $query, array $columns, string $term): void
    {
        foreach ($columns as $column) {
            $query->orWhere($column, 'like', "%{$term}%");
        }
    }

    /**
     * A term that reads as a phone number also finds the number written
     * another way: "01711-000 000" finds "01711 000000". Only its digits are
     * compared, at least three of them, and only when the term holds nothing
     * but what phone numbers are written with.
     *
     * @param  list<string>  $columns
     */
    private function samePhone(Builder $query, array $columns, string $term): void
    {
        $digits = preg_replace('/\D+/', '', $term);

        if (strlen($digits) < 3 || preg_match('/[^\d\s()+.\-]/', $term)) {
            return;
        }

        foreach ($columns as $column) {
            $query->orWhereRaw(
                "replace(replace(replace(replace(replace(replace({$column}, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', '') like ?",
                ["%{$digits}%"],
            );
        }
    }
}
