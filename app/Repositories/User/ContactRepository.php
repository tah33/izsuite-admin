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
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage, bool $withTaxId): LengthAwarePaginator
    {
        return $this->queryFor($workspace, $filters, $withTaxId)->paginate($perPage);
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
            ->when(filter_var($filters['for_sale'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('role', '!=', Contact::ROLE_SUPPLIER)
                ->where('is_active', true))
            ->orderBy('name')
            ->orderBy('id');
    }

    public function findFor(Workspace $workspace, int $id): ?Contact
    {
        return $workspace->contacts()->find($id);
    }

    /**
     * The business's contacts that $probe might be another copy of - up to
     * twenty, alphabetical. $probe holds what to compare, already tidied (see
     * ContactDuplicates): names (lower case), email (lower case), phones (the
     * last digits of each number) and tax (lower case, without separators).
     * Nothing to compare finds nothing.
     */
    public function possibleDuplicatesOf(Workspace $workspace, array $probe, ?int $exceptId): Collection
    {
        if (! array_filter($probe)) {
            return new Collection;
        }

        return Contact::where('workspace_id', $workspace->id)
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
