<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contacts belong to one business (workspace). Every lookup is scoped to it, so
 * another business's contact - the same owner's or anyone else's - is simply
 * not found.
 */
class ContactRepository
{
    /**
     * A page of the business's contacts, alphabetical with the id as
     * tie-breaker. $filters: search (name, company, e-mail or phone), type
     * (customer or vendor - a contact that is both is in each) and status
     * (active or inactive).
     */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return Contact::where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->ofType($type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id): ?Contact
    {
        return $workspace->contacts()->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): Contact
    {
        return $workspace->contacts()->create($data);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }
}
