<?php

namespace App\Services\Api\Profile;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\ConsentRecords;
use App\Services\Support\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * My Profile (Doc 12 section 22): the settings that are the user's own -
 * name, phone, preferred language, personal time zone and profile image -
 * the same in every business they belong to.
 */
class ProfileService
{
    /** The user's own details a business keeps a copy of, besides the name. */
    private const DETAILS = ['phone', 'language', 'timezone'];

    public function __construct(
        protected UserRepository $userRepository,
        protected WorkspaceMemberRepository $memberRepository,
        protected ImageService $imageService,
        protected ConsentRecords $consents,
    ) {}

    /** The signed-in account, with its role, for the profile page. */
    public function show(User $user): User
    {
        return $this->userRepository->loadRole($user);
    }

    /**
     * Save the editable profile fields of the signed-in account.
     *
     * $data is the validated request, so it can only ever hold the fields
     * UpdateProfileRequest allows - role, status and the rest stay out of reach
     * even though they are mass assignable.
     *
     * What the user changes is theirs in every business: each business's
     * record of them takes the new name, phone, language or time zone, so its
     * Users list shows them. What they left alone stays as each business
     * has it, and so does the job title, which is the business's.
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // Product news by e-mail is consent, recorded when it changes (Doc 15 Phase G3).
            if (array_key_exists('marketing', $data)) {
                $this->consents->setMarketing($user, (bool) $data['marketing'], 'profile');
            }

            $updated = $this->userRepository->update($user, Arr::except($data, ['marketing']));
            $changed = array_keys(array_diff_key($user->getChanges(), ['updated_at' => true]));
            $details = [];

            if (array_intersect(['first_name', 'last_name'], $changed)) {
                $details['name'] = mb_substr($updated->name, 0, 150);
            }

            foreach (array_intersect(self::DETAILS, $changed) as $field) {
                $details[$field] = $updated->{$field};
            }

            if ($details) {
                $this->memberRepository->updateDetailsOf($updated, $details);
            }

            // Logged even when nothing changed: LogApiWriteActivity would otherwise
            // write its own, vaguer entry for the successful PUT. `fields` is what
            // tells the two cases apart.
            ActivityLogService::record('updated', 'Updated their profile', $updated, [
                'source' => 'api',
                'fields' => $changed,
            ]);

            return $this->userRepository->loadRole($updated);
        });
    }

    /** A new profile image in the place of the old one, which is deleted. */
    public function changeImage(User $user, UploadedFile $image): User
    {
        $path    = $this->imageService->storePublic($image, 'avatars', $user->avatar);
        $updated = $this->userRepository->update($user, ['avatar' => $path]);

        ActivityLogService::record('updated', 'Changed their profile image', $updated, ['source' => 'api', 'fields' => ['avatar']]);

        return $updated;
    }

    /** No profile image: the first letter of the name shows instead. */
    public function removeImage(User $user): User
    {
        $this->imageService->deletePublic($user->avatar);

        $updated = $this->userRepository->update($user, ['avatar' => null]);

        ActivityLogService::record('updated', 'Removed their profile image', $updated, ['source' => 'api', 'fields' => ['avatar']]);

        return $updated;
    }
}
