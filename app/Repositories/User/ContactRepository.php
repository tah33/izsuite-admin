<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contacts belong to one business (workspace). Every lookup is scoped to it, so
 * another business's contact - the same owner's or anyone else's - is simply
 * not found.
 */
class ContactRepository
{
    /** Whether a sales document, a purchase or an expense names it - what ContactResource's `in_use`, `in_sales` and `in_purchases` are made of. */
    private const USED_ON = ['salesDocuments as in_sales', 'purchases as in_purchases', 'expenses as in_expenses'];

    /**
     * A page of the business's contacts, alphabetical with the id as
     * tie-breaker. $filters (Doc 03 section 3):
     *
     * - search: the name or trading name, the Contact Number, the e-mail, a
     *   phone number - typed with or without its spaces and dashes - and, when
     *   $withTaxId, the tax ID.
     * - role: customer, supplier or both - exactly; "customer" leaves out the
     *   contacts that are both.
     * - type: business or individual.
     * - status: active or inactive.
     * - tax_treatment, when $withTaxId: exactly.
     * - unmerged: leaving out those merged into another (Doc 17 Phase C3).
     *
     * Each comes with `in_sales` and `in_purchases`: whether a sales document
     * or a purchase names it.
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage, bool $withTaxId): LengthAwarePaginator
    {
        return $this->queryFor($workspace, $filters, $withTaxId)->withExists(self::USED_ON)->paginate($perPage);
    }

    /** How many contacts the list holds with $filters - for an export. */
    public function countFor(Workspace $workspace, array $filters, bool $withTaxId): int
    {
        return $this->queryFor($workspace, $filters, $withTaxId)->count();
    }

    /** The list's contacts with $filters, read 500 at a time - for an export. */
    public function lazyFor(Workspace $workspace, array $filters, bool $withTaxId): LazyCollection
    {
        return $this->queryFor($workspace, $filters, $withTaxId)->lazy(500);
    }

    /** The list's contacts, searched and filtered the same way, in its order - for an export, whole. */
    public function queryFor(Workspace $workspace, array $filters, bool $withTaxId): Builder
    {
        return Contact::where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $this->search($query, $search, $withTaxId)))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->ofRole($role))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('contact_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->when($withTaxId && ($filters['tax_treatment'] ?? null), fn (Builder $query) => $query->where('tax_treatment', $filters['tax_treatment']))
            ->when(filter_var($filters['unmerged'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query->whereNull('merged_into_id'))
            ->when(filter_var($filters['for_sale'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('role', '!=', Contact::ROLE_SUPPLIER)
                ->where('is_active', true))
            ->when(filter_var($filters['for_purchase'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('role', '!=', Contact::ROLE_CUSTOMER)
                ->where('is_active', true))
            ->orderBy('name')
            ->orderBy('id');
    }

    /** With `in_sales` and `in_purchases`, as the list has them. */
    public function findFor(Workspace $workspace, int $id): ?Contact
    {
        return $workspace->contacts()->withExists(self::USED_ON)->find($id);
    }

    /** Whether another contact was merged into it (Doc 17 Phase C3). */
    public function hasMergedContacts(Contact $contact): bool
    {
        return Contact::where('merged_into_id', $contact->id)->exists();
    }

    /** For its page (Doc 17 Phase C3): the contact it was merged into, and who did it, or those merged into it. */
    public function loadMerges(Contact $contact): Contact
    {
        return $contact->load([
            'mergedInto:id,contact_number,name',
            'merger:id,first_name,last_name',
            'mergedContacts:id,contact_number,name,merged_into_id,merged_at',
        ]);
    }

    /** The business's contacts among $ids, and any with a credit limit - Customer Credit Exposure's (Doc 16 Phase H3c). */
    public function creditCustomers(Workspace $workspace, array $ids): Collection
    {
        return $workspace->contacts()
            ->where(fn (Builder $query) => $query->whereIn('id', $ids)->orWhereNotNull('credit_limit'))
            ->orderBy('name')
            ->get();
    }

    /**
     * The business's contacts that $probe might be another copy of - up to
     * twenty, alphabetical. $probe holds what to compare, already tidied (see
     * ContactDuplicates): names (lower case), email (lower case), phones (the
     * last digits of each number) and tax (lower case, without separators).
     * Nothing to compare finds nothing. A contact merged into another is not
     * one: the one it was merged into is.
     */
    public function possibleDuplicatesOf(Workspace $workspace, array $probe, ?int $exceptId): Collection
    {
        if (! array_filter($probe)) {
            return new Collection;
        }

        return Contact::where('workspace_id', $workspace->id)
            ->whereNull('merged_into_id')
            ->when($exceptId, fn (Builder $query, int $id) => $query->where('id', '!=', $id))
            ->where(function (Builder $query) use ($probe) {
                foreach ($probe['names'] as $name) {
                    $query->orWhereRaw('LOWER(TRIM(name)) = ?', [$name])
                        ->orWhereRaw('LOWER(TRIM(trading_name)) = ?', [$name]);
                }

                if ($probe['email']) {
                    $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$probe['email']]);
                }

                foreach ($probe['phones'] as $digits) {
                    $query->orWhereRaw($this->digitsOf('phone').' LIKE ?', ["%{$digits}"])
                        ->orWhereRaw($this->digitsOf('alt_phone').' LIKE ?', ["%{$digits}"]);
                }

                if ($probe['tax']) {
                    $query->orWhereRaw('LOWER('.$this->strip('tax_number', [' ', '-', '.', '/']).') = ?', [$probe['tax']]);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    /**
     * The business's contacts that share any of $names (their name or
     * trading name), $emails or $phones (a phone's last digits) - tidied as
     * possibleDuplicatesOf() takes them. For a page of leads at once (Doc 17
     * Phase C2): which of them may already be contacts - not those merged
     * into another.
     *
     * @param  list<string>  $names
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     */
    public function matchingAny(Workspace $workspace, array $names, array $emails, array $phones): Collection
    {
        if (! $names && ! $emails && ! $phones) {
            return new Collection;
        }

        return Contact::where('workspace_id', $workspace->id)
            ->whereNull('merged_into_id')
            ->where(function (Builder $query) use ($names, $emails, $phones) {
                if ($names) {
                    $placeholders = implode(', ', array_fill(0, count($names), '?'));
                    $query->orWhereRaw("LOWER(TRIM(name)) IN ({$placeholders})", $names)
                        ->orWhereRaw("LOWER(TRIM(trading_name)) IN ({$placeholders})", $names);
                }

                if ($emails) {
                    $query->orWhereRaw('LOWER(TRIM(email)) IN ('.implode(', ', array_fill(0, count($emails), '?')).')', $emails);
                }

                foreach ($phones as $digits) {
                    $query->orWhereRaw($this->digitsOf('phone').' LIKE ?', ["%{$digits}"])
                        ->orWhereRaw($this->digitsOf('alt_phone').' LIKE ?', ["%{$digits}"]);
                }
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'contact_number', 'name', 'trading_name', 'email', 'phone', 'alt_phone']);
    }

    /**
     * Doc 19 Phase E2: up to $limit of $workspace's active customers - not
     * merged away, a customer or both - whose e-mail is $email, whatever its
     * case and spaces.
     */
    public function customersWithEmail(Workspace $workspace, string $email, int $limit = 2): Collection
    {
        return Contact::where('workspace_id', $workspace->id)
            ->whereNull('merged_into_id')
            ->where('is_active', true)
            ->whereIn('role', [Contact::ROLE_CUSTOMER, Contact::ROLE_BOTH])
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email))])
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** Doc 19 Phase E2: $workspace's contact $id - or the one it was merged into - while active; else none. */
    public function findCurrent(Workspace $workspace, int $id): ?Contact
    {
        $contact = Contact::where('workspace_id', $workspace->id)->find($id);

        while ($contact && $contact->merged_into_id) {
            $contact = Contact::where('workspace_id', $workspace->id)->find($contact->merged_into_id);
        }

        return $contact?->is_active ? $contact : null;
    }

    /** Of $ids, the business's contacts, by id: those a decision names that no longer match. */
    public function findManyFor(Workspace $workspace, array $ids): Collection
    {
        return $ids === [] ? new Collection : Contact::where('workspace_id', $workspace->id)
            ->whereIn('id', $ids)
            ->get(['id', 'contact_number', 'name', 'trading_name', 'email', 'phone', 'alt_phone'])
            ->keyBy('id');
    }

    /**
     * Made through the business, so its workspace is never read from $data.
     * Its Contact Number is given as it is saved (see Contact).
     */
    public function create(Workspace $workspace, array $data): Contact
    {
        return $workspace->contacts()->create($data);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact;
    }

    /** Clears $column - a default currency - on the contacts where it is outside $currencies. */
    public function clearCurrencyOutside(Workspace $workspace, string $column, array $currencies): void
    {
        $workspace->contacts()->whereNotNull($column)->whereNotIn($column, $currencies)->update([$column => null]);
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }

    private function search(Builder $query, string $search, bool $withTaxId): void
    {
        $like = "%{$search}%";

        $query->where('name', 'like', $like)
            ->orWhere('trading_name', 'like', $like)
            ->orWhere('contact_number', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('alt_phone', 'like', $like);

        // Something typed like a phone number finds it however it was stored:
        // "01711 000000" finds "+880 1711-000000".
        $digits = preg_replace('/\D/', '', $search);

        if (strlen($digits) >= 4 && preg_match('/^[\d\s()+.\-]+$/', $search)) {
            $query->orWhereRaw($this->digitsOf('phone').' LIKE ?', ["%{$digits}%"])
                ->orWhereRaw($this->digitsOf('alt_phone').' LIKE ?', ["%{$digits}%"]);
        }

        if ($withTaxId) {
            $query->orWhere('tax_number', 'like', $like);
        }
    }

    /** A phone column with only its digits left. */
    private function digitsOf(string $column): string
    {
        return $this->strip($column, [' ', '-', '(', ')', '.', '+']);
    }

    /** SQL for $column with each of $characters taken out - the same in MySQL and SQLite. */
    private function strip(string $column, array $characters): string
    {
        $sql = "COALESCE({$column}, '')";

        foreach ($characters as $character) {
            $sql = "REPLACE({$sql}, '{$character}', '')";
        }

        return $sql;
    }
}
