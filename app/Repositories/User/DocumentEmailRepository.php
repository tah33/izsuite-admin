<?php

namespace App\Repositories\User;

use App\Models\User\DocumentEmail;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;

/** The emails a business sent with its documents - written as they are queued, updated as they go. */
class DocumentEmailRepository
{
    /** Made through the business, for $record, so neither is read from $data. */
    public function create(Workspace $workspace, Model $record, array $data): DocumentEmail
    {
        return $workspace->documentEmails()->create([
            ...$data,
            'emailable_type' => $record->getMorphClass(),
            'emailable_id'   => $record->getKey(),
        ]);
    }

    /** For the job that sends it: the email with what it carries. */
    public function findWithRecord(int $id): ?DocumentEmail
    {
        return DocumentEmail::query()->with(['emailable', 'workspace', 'sender:id,first_name,last_name'])->find($id);
    }

    public function update(DocumentEmail $email, array $data): void
    {
        $email->update($data);
    }
}
