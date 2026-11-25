<?php

namespace App\Services\Shared;

use App\Services\Support\ImageService;
use Illuminate\Http\UploadedFile;

/**
 * A user's profile image (`users.avatar`), set from a form whose x-file-picker
 * is named "avatar": an upload replaces it, the picker's Remove
 * (`remove_avatar`) takes it away, and with neither it stays. Kept on the
 * public disk beside a client's (Api\Profile\ProfileService).
 */
class ProfileImages
{
    private const DIRECTORY = 'avatars';

    public function __construct(
        protected ImageService $images,
    ) {}

    /**
     * $data ready to save: an uploaded avatar stored and its path in its place,
     * null for a removed one, or no 'avatar' at all so the row keeps its own.
     */
    public function fill(array $data): array
    {
        $image  = $data['avatar'] ?? null;
        $remove = ! empty($data['remove_avatar']);
        unset($data['avatar'], $data['remove_avatar']);

        if ($image instanceof UploadedFile && $image->isValid()) {
            $data['avatar'] = $this->images->storePublic($image, self::DIRECTORY);
        } elseif ($remove) {
            $data['avatar'] = null;
        }

        return $data;
    }

    /**
     * Once $saved - what fill() gave - is on the row: the image it had before,
     * $old, goes if it was replaced or removed. Not sooner, so a save that
     * fails leaves the row pointing at a file that is still there.
     */
    public function discardOld(?string $old, array $saved): void
    {
        if (array_key_exists('avatar', $saved) && $old !== $saved['avatar']) {
            $this->images->deletePublic($old);
        }
    }

    /** The image of an account that is gone. */
    public function delete(?string $path): void
    {
        $this->images->deletePublic($path);
    }
}
