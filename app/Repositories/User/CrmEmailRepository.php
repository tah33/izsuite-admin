<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmEmail;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use Illuminate\Database\Eloquent\Collection;

/** E-mails sent from a lead, an opportunity or a contact (Doc 17 Phase F3a). */
class CrmEmailRepository
{
    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmEmail
    {
        $email = new CrmEmail($data);
        $email->workspace_id = $workspace->id;
        $email->save();

        return $email;
    }

    /** One to send, with its business and the record it was sent from. */
    public function findToSend(int $id): ?CrmEmail
    {
        return CrmEmail::query()->with(['workspace', 'lead', 'opportunity', 'contact'])->find($id);
    }

    public function update(CrmEmail $email, array $data): void
    {
        $email->update($data);
    }

    /**
     * The sales documents of $ids that $email's business has - what it
     * carries as PDFs - each with what its PDF shows.
     *
     * @param  list<int>  $ids
     * @return Collection<int, SalesDocument>
     */
    public function documents(CrmEmail $email, array $ids): Collection
    {
        return SalesDocument::query()
            ->where('workspace_id', $email->workspace_id)
            ->whereIn('id', $ids)
            ->with(['lines', 'contact', 'location:id,name', 'source:id,type,number'])
            ->get();
    }

    /**
     * The files of $ids that $email's business has.
     *
     * @param  list<int>  $ids
     * @return Collection<int, WorkspaceFile>
     */
    public function files(CrmEmail $email, array $ids): Collection
    {
        return WorkspaceFile::query()->where('workspace_id', $email->workspace_id)->whereIn('id', $ids)->get();
    }

    /** Whether any was sent from $contact: it is then deactivated, not deleted. */
    public function usesContact(Contact $contact): bool
    {
        return CrmEmail::query()->where('contact_id', $contact->id)->exists();
    }
}
