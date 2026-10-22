<?php

namespace App\Services\Api\Contacts;

use App\Models\User\Contact;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ContactService
{
    public function __construct(
        protected ContactRepository $contactRepository,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->contactRepository->paginateFor($workspace, $filters, $perPage);
    }

    public function create(Workspace $workspace, array $data): Contact
    {
        $contact = $this->contactRepository->create($workspace, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/contacts" entry for the same request.
        ActivityLogService::record('created', "Added contact {$this->quoted($contact)}", $contact, ['source' => 'api']);

        return $contact;
    }

    public function update(Workspace $workspace, int $id, array $data): Contact
    {
        $contact = $this->findOwned($workspace, $id);

        $this->contactRepository->update($contact, $data);

        ActivityLogService::record('updated', "Updated contact {$this->quoted($contact)}", $contact, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($contact->getChanges(), ['updated_at' => true])),
        ]);

        return $contact;
    }

    /**
     * Nothing points at a contact yet. When documents do (invoices, bills),
     * one that is in use must be made inactive instead of deleted.
     */
    public function delete(Workspace $workspace, int $id): void
    {
        $contact = $this->findOwned($workspace, $id);

        $this->contactRepository->delete($contact);

        ActivityLogService::record('deleted', "Deleted contact {$this->quoted($contact)}", $contact, ['source' => 'api']);
    }

    /**
     * A contact of another business - the owner's own other one, or someone
     * else's - answers exactly like one that does not exist. Aborting with our
     * own message also keeps Laravel's default out of the response, which
     * names an internal class.
     */
    private function findOwned(Workspace $workspace, int $id): Contact
    {
        return $this->contactRepository->findFor($workspace, $id)
            ?? abort(404, 'Contact not found.');
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(Contact $contact): string
    {
        return '"'.Str::limit($contact->name, 100).'"';
    }
}
