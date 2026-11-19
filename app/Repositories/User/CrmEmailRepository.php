<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmEmail;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use Carbon\CarbonInterface;
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

    /**
     * When the latest e-mail from a record - one whose $column ("lead_id" or
     * "opportunity_id") is $id - was sent from izSuite (Phase G3a).
     */
    public function lastSentAt(Workspace $workspace, string $column, int $id): ?CarbonInterface
    {
        return CrmEmail::query()
            ->where('workspace_id', $workspace->id)
            ->where($column, $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'created_at'])
            ?->created_at;
    }

    /**
     * When the latest e-mail to each of opportunities $ids was sent from
     * izSuite (Phase H3), by opportunity id: as lastSentAt(), for many.
     *
     * @return array<int, string>
     */
    public function lastSentByOpportunity(Workspace $workspace, array $ids): array
    {
        return CrmEmail::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('opportunity_id', $ids)
            ->groupBy('opportunity_id')
            ->selectRaw('opportunity_id, max(created_at) as last_at')
            ->pluck('last_at', 'opportunity_id')
            ->all();
    }

    /**
     * How many e-mails were sent to each of leads $ids from izSuite, and when
     * the latest was (Phase I2's lead scoring), by lead id - none for a lead
     * never e-mailed.
     *
     * @return array<int, array{count: int, last_at: string}>
     */
    public function sentByLead(Workspace $workspace, array $ids): array
    {
        return CrmEmail::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('lead_id', $ids)
            ->groupBy('lead_id')
            ->selectRaw('lead_id, count(*) as sent_count, max(created_at) as last_at')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->lead_id => ['count' => (int) $row->sent_count, 'last_at' => (string) $row->last_at]])
            ->all();
    }

    /** Whether any was sent from $contact: it is then deactivated, not deleted. */
    public function usesContact(Contact $contact): bool
    {
        return CrmEmail::query()->where('contact_id', $contact->id)->exists();
    }
}
