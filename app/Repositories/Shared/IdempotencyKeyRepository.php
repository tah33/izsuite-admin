<?php

namespace App\Repositories\Shared;

use App\Models\Shared\IdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;

class IdempotencyKeyRepository
{
    /**
     * $data's key taken for its user - or, taken already, null: another
     * request with it came first.
     */
    public function claim(array $data): ?IdempotencyKey
    {
        try {
            return IdempotencyKey::create($data);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function find(int $userId, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::where('user_id', $userId)->where('key', $key)->first();
    }

    /** The first request's answer, kept for repeats. */
    public function answer(IdempotencyKey $record, int $status, string $body): void
    {
        $record->update(['status_code' => $status, 'response' => $body]);
    }

    /** The key given back: its request failed, so sending it again may try once more. */
    public function release(IdempotencyKey $record): void
    {
        $record->delete();
    }
}
