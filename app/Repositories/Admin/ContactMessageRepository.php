<?php

namespace App\Repositories\Admin;

use App\Models\Admin\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ContactMessageRepository
{
    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return ContactMessage::query()
            ->with(['user', 'replier'])
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function find(int $id): ?ContactMessage
    {
        return ContactMessage::with(['user', 'replier'])->find($id);
    }

    public function findForUser(int $id, int $userId): ?ContactMessage
    {
        return ContactMessage::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function getForUser(int $userId): Collection
    {
        return ContactMessage::query()
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function create(array $data): ContactMessage
    {
        return ContactMessage::create($data);
    }

    public function update(ContactMessage $contactMessage, array $data): ContactMessage
    {
        $contactMessage->update($data);

        return $contactMessage->fresh(['user', 'replier']);
    }
}
