<?php

namespace App\Services\Api\BusinessSettings;

use App\Enums\FileCollection;
use App\Models\User\BusinessSetting;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Services\Files\FileStorage;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\UploadedFile;

class BusinessSettingService
{
    public function __construct(
        protected BusinessSettingRepository $businessSettingRepository,
        protected FileStorage $fileStorage,
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
     * uploaded file: it becomes the workspace's logo file, replacing the one
     * before - first, so a business with no storage left is told before
     * anything else changes. No new file keeps the current logo.
     */
    public function save(Workspace $workspace, array $validated): BusinessSetting
    {
        $upload = $validated['logo'] ?? null;
        unset($validated['logo']);

        if ($upload instanceof UploadedFile) {
            $this->fileStorage->store($workspace, $workspace, FileCollection::Logo, [$upload], 'logo');
        }

        $settings = $this->businessSettingRepository->save($workspace, $validated);

        // Logged here, with the fields involved, so LogApiWriteActivity does not
        // write its own vaguer "POST api/v1/business-settings" entry for the
        // same request. Field names only - never the values.
        //
        // A new row has no changes to read back (Eloquent only tracks those on
        // update), so for the first save the fields it was created with stand in.
        $fields = $settings->wasRecentlyCreated
            ? array_keys($validated)
            : array_keys(array_diff_key($settings->getChanges(), ['updated_at' => true]));

        ActivityLogService::record(
            $settings->wasRecentlyCreated ? 'created' : 'updated',
            $settings->wasRecentlyCreated ? 'Created their business settings' : 'Updated their business settings',
            $settings,
            [
                'source' => 'api',
                'fields' => $upload instanceof UploadedFile ? [...$fields, 'logo'] : $fields,
            ],
        );

        return $settings;
    }
}
