<?php

namespace App\Services\Api\BusinessSettings;

use App\Models\User\BusinessSetting;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Http\UploadedFile;

class BusinessSettingService
{
    /** Where uploaded logos live on the public disk. */
    private const LOGO_DIRECTORY = 'business-logos';

    public function __construct(
        protected BusinessSettingRepository $businessSettingRepository,
        protected ImageService $imageService,
    ) {}

    public function show(Workspace $workspace): ?BusinessSetting
    {
        return $this->businessSettingRepository->findFor($workspace);
    }

    /**
     * Save the business's settings, creating them the first time.
     *
     * $validated is the validated request, so it can only hold the fields
     * SaveBusinessSettingsRequest allows. Its `logo`, when present, is the
     * uploaded file rather than a stored path - it is swapped for the path
     * here, and left out entirely when no new file came, which keeps the
     * current logo.
     */
    public function save(Workspace $workspace, array $validated): BusinessSetting
    {
        $upload = $validated['logo'] ?? null;
        unset($validated['logo']);

        $previousLogo = $this->businessSettingRepository->findFor($workspace)?->logo;

        // The new file goes down first and the old one is only removed once the
        // row points at the new one - a failed write must not leave the business
        // with a logo path whose file is already gone, nor a file nothing uses.
        $newLogo = $upload instanceof UploadedFile
            ? $this->imageService->storePublic($upload, self::LOGO_DIRECTORY)
            : null;

        $attributes = $newLogo ? [...$validated, 'logo' => $newLogo] : $validated;

        try {
            $settings = $this->businessSettingRepository->save($workspace, $attributes);
        } catch (\Throwable $e) {
            $this->imageService->deletePublic($newLogo);

            throw $e;
        }

        if ($newLogo) {
            $this->imageService->deletePublic($previousLogo);
        }

        // Logged here, with the fields involved, so LogApiWriteActivity does not
        // write its own vaguer "POST api/v1/business-settings" entry for the
        // same request. Field names only - never the values.
        //
        // A new row has no changes to read back (Eloquent only tracks those on
        // update), so for the first save the fields it was created with stand in.
        ActivityLogService::record(
            $settings->wasRecentlyCreated ? 'created' : 'updated',
            $settings->wasRecentlyCreated ? 'Created their business settings' : 'Updated their business settings',
            $settings,
            [
                'source' => 'api',
                'fields' => $settings->wasRecentlyCreated
                    ? array_keys($attributes)
                    : array_keys(array_diff_key($settings->getChanges(), ['updated_at' => true])),
            ],
        );

        return $settings;
    }
}
