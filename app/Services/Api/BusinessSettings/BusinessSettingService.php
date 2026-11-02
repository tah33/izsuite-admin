<?php

namespace App\Services\Api\BusinessSettings;

use App\Enums\FileCollection;
use App\Models\User\BusinessSetting;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Files\FileStorage;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessActivity;
use App\Support\Countries;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class BusinessSettingService
{
    public function __construct(
        protected BusinessSettingRepository $businessSettingRepository,
        protected FileStorage $fileStorage,
        protected BusinessActivity $businessActivity,
        protected WorkspaceRepository $workspaceRepository,
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
     *
     * Once the business has transacted, a new country is asked about first
     * (Doc 11 section 5): a 422 on `country_change` until it is confirmed.
     * A new time zone renews the sidebar, which carries it to every page.
     */
    public function save(Workspace $workspace, array $validated): BusinessSetting
    {
        $upload    = $validated['logo'] ?? null;
        $confirmed = (bool) ($validated['confirm_country_change'] ?? false);
        unset($validated['logo'], $validated['confirm_country_change']);

        $before = $this->businessSettingRepository->findFor($workspace);

        if ($before && $before->country !== $validated['country'] && ! $confirmed && $this->businessActivity->exists($workspace)) {
            $was = Countries::name($before->country) ?? $before->country;

            throw ValidationException::withMessages([
                'country_change' => "Your business has transactions from when it was in {$was}. They stay as they are; new documents follow the new country. Change it anyway?",
            ]);
        }

        if ($upload instanceof UploadedFile) {
            $this->fileStorage->store($workspace, $workspace, FileCollection::Logo, [$upload], 'logo');
        }

        $settings = $this->businessSettingRepository->save($workspace, $validated);

        if ($settings->wasRecentlyCreated || $settings->wasChanged('timezone')) {
            $this->workspaceRepository->bumpNavVersion($workspace);
        }

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
