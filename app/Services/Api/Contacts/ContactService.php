<?php

namespace App\Services\Api\Contacts;

use App\Services\Shared\EditConflicts;
use App\Models\User\Contact;
use App\Models\User\Workspace;
use App\Repositories\Shared\RecordAuthorRepository;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\ExpenseRepository;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ContactService
{
    public function __construct(
        protected ContactRepository $contactRepository,
        protected ContactDuplicates $contactDuplicates,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected PurchaseRepository $purchaseRepository,
        protected ExpenseRepository $expenseRepository,
        protected WorkspaceAccess $access,
        protected RecordAuthorRepository $recordAuthorRepository,
        protected CrmOpportunityRepository $crmOpportunityRepository,
        protected \App\Repositories\User\CrmActivityRepository $crmActivityRepository,
        protected \App\Repositories\User\CrmEmailRepository $crmEmailRepository,
    ) {}

    /** The tax ID is searched only while the business uses taxes (Doc 03 section 3). */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->contactRepository->paginateFor($workspace, $filters, $perPage, $workspace->usesTaxes() && $this->access->can('taxes.view'));
    }

    /**
     * For its own page: with who added it and who last changed it (Doc 14
     * section 14.5), and the contact it was merged into or those merged into
     * it (Doc 17 Phase C3).
     */
    public function find(Workspace $workspace, int $id): Contact
    {
        return $this->recordAuthorRepository->load($this->contactRepository->loadMerges($this->findOwned($workspace, $id)));
    }

    /** One to change - edit, archive, delete, give attachments: not one merged into another. */
    public function findChangeable(Workspace $workspace, int $id): Contact
    {
        $contact = $this->findOwned($workspace, $id);

        $this->ensureChangeable($contact);

        return $contact;
    }

    /**
     * A contact merged into another (Doc 17 Phase C3) is kept as it was, for
     * its records: the one it was merged into is the one that changes.
     */
    public function ensureChangeable(Contact $contact): void
    {
        if (! $contact->isMerged()) {
            return;
        }

        $into = $this->contactRepository->loadMerges($contact)->mergedInto;

        throw new HttpResponseException(response()->json([
            'message' => 'This contact was merged into '.($into ? $this->quoted($into) : 'another contact').', so it no longer changes.',
        ], 409));
    }

    /**
     * A contact that looks like one the business already has is not saved
     * until the user has seen which (see warnOfDuplicates) and asked to save it
     * anyway, with allow_duplicates.
     */
    public function create(Workspace $workspace, array $data): Contact
    {
        $allowDuplicates = (bool) Arr::pull($data, 'allow_duplicates', false);

        if (! $allowDuplicates) {
            $this->warnOfDuplicates($workspace, $data);
        }

        $contact = $this->contactRepository->create($workspace, $this->withoutOwnShipping($data));

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/contacts" entry for the same request.
        ActivityLogService::record('created', "Added contact {$this->quoted($contact)}", $contact, ['source' => 'api']);

        return $contact;
    }

    /**
     * A contact from an import: checked, and its possible duplicates already
     * shown. Added without a log entry of its own - the import logs once.
     */
    public function import(Workspace $workspace, array $data): Contact
    {
        return $this->contactRepository->create($workspace, $this->withoutOwnShipping($data));
    }

    /**
     * Checked for duplicates again only when the edit changes what could make
     * it look like another contact - a new address does not repeat a warning
     * that was already given.
     */
    public function update(Workspace $workspace, int $id, array $data): Contact
    {
        $contact         = $this->findChangeable($workspace, $id);

        // Changed by someone else since it was opened: refused (Doc 14 section 22).
        EditConflicts::ensureUnchanged($contact, 'this contact');
        $allowDuplicates = (bool) Arr::pull($data, 'allow_duplicates', false);

        if (! $allowDuplicates && $this->changesWhatMatches($contact, $data)) {
            $this->warnOfDuplicates($workspace, [...$contact->only(ContactDuplicates::FIELDS), ...Arr::only($data, ContactDuplicates::FIELDS)], $contact);
        }

        $this->contactRepository->update($contact, $this->withoutOwnShipping($data, $contact));

        ActivityLogService::record('updated', "Updated contact {$this->quoted($contact)}", $contact, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($contact->getChanges(), ['updated_at' => true])),
        ]);

        return $contact;
    }

    /** Active: offered for new transactions. Inactive: kept, with its history, but not offered. */
    public function setActive(Workspace $workspace, int $id, bool $active): Contact
    {
        $contact = $this->findChangeable($workspace, $id);

        $this->contactRepository->update($contact, ['is_active' => $active]);

        ActivityLogService::record('updated', ($active ? 'Restored' : 'Archived')." contact {$this->quoted($contact)}", $contact, [
            'source' => 'api',
            'fields' => $contact->wasChanged('is_active') ? ['is_active'] : [],
        ]);

        return $contact;
    }

    /** Only an unused contact can be deleted (Doc 03 section 13): one on a sales document, a purchase or an expense is deactivated instead. */
    public function delete(Workspace $workspace, int $id): void
    {
        $contact = $this->findChangeable($workspace, $id);

        if ($this->salesDocumentRepository->usesContact($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contact is on sales documents, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        if ($this->purchaseRepository->usesContact($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contact is on purchases, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        if ($this->expenseRepository->usesContact($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contact was paid on expenses, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        // Doc 17 Phase C1: an opportunity is always for a contact.
        if ($this->crmOpportunityRepository->usesContact($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contact has CRM opportunities, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        // Doc 17 Phase E1: an activity planned for it.
        if ($this->crmActivityRepository->usesContact($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contact has CRM activities, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        // Doc 17 Phase F3a: an e-mail sent from it, kept on its timeline.
        if ($this->crmEmailRepository->usesContact($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This contact has CRM e-mails, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        // Doc 17 Phase C3: the contacts merged into it point at it for good.
        if ($this->contactRepository->hasMergedContacts($contact)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Other contacts were merged into this one, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        $this->contactRepository->delete($contact);

        ActivityLogService::record('deleted', "Deleted contact {$this->quoted($contact)}", $contact, ['source' => 'api']);
    }

    /**
     * Stops the save with a 409 that lists the contacts this one might be
     * another copy of. The form shows them and can send the save again with
     * allow_duplicates.
     */
    private function warnOfDuplicates(Workspace $workspace, array $fields, ?Contact $except = null): void
    {
        $duplicates = $this->contactDuplicates->find($workspace, $fields, $except);

        if ($duplicates) {
            throw new HttpResponseException(response()->json([
                'message'    => 'This looks like a contact you already have.',
                'duplicates' => $duplicates,
            ], 409));
        }
    }

    private function changesWhatMatches(Contact $contact, array $data): bool
    {
        foreach (ContactDuplicates::FIELDS as $field) {
            if (array_key_exists($field, $data) && trim((string) $data[$field]) !== trim((string) $contact->{$field})) {
                return true;
            }
        }

        return false;
    }

    /** A shipping address that is the billing one keeps no fields of its own. */
    private function withoutOwnShipping(array $data, ?Contact $contact = null): array
    {
        if ($data['shipping_same_as_billing'] ?? $contact?->shipping_same_as_billing ?? true) {
            $data = [...$data, ...array_fill_keys(Contact::SHIPPING_FIELDS, null)];
        }

        return $data;
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
