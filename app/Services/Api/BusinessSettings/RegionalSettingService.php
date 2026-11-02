<?php

namespace App\Services\Api\BusinessSettings;

use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\DocumentTemplateRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\RegionalDefaults;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Settings > Regional & Localization (Doc 11 section 5): how the business
 * writes dates, times and numbers, the day its week starts, its paper and the
 * month its fiscal year starts - with what its country recommends. Every page
 * shows dates and numbers this way, and a template it has never changed
 * starts from them; asked to, the ones it has changed take them too.
 * Documents already numbered keep what they printed. The country, language and
 * time zone are the business profile's.
 */
class RegionalSettingService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected BusinessSettingRepository $businessSettingRepository,
        protected DocumentTemplateRepository $documentTemplateRepository,
    ) {}

    public function show(Workspace $workspace): array
    {
        $country = $this->businessSettingRepository->findFor($workspace)?->country;

        return [
            ...$workspace->regional(),
            'country'     => $country,
            'recommended' => RegionalDefaults::for($country),
        ];
    }

    /** $data is validated (see SaveRegionalSettingsRequest). */
    public function save(Workspace $workspace, array $data): array
    {
        $settings = Arr::only($data, array_keys(RegionalDefaults::BASE));

        DB::transaction(function () use ($workspace, $data, $settings) {
            $this->workspaceRepository->forceUpdate($workspace, $settings);

            if ($data['apply_to_templates'] ?? false) {
                $this->documentTemplateRepository->applyFormats($workspace, Arr::only($settings, ['date_format', 'number_format', 'paper_size']));
            }
        });

        // Logged here, like every save, so LogApiWriteActivity does not write its own vaguer entry.
        ActivityLogService::record('updated', 'Updated the regional settings', $workspace, [
            'source'    => 'api',
            'fields'    => array_keys(array_diff_key($workspace->getChanges(), ['updated_at' => true, 'nav_version' => true])),
            'templates' => (bool) ($data['apply_to_templates'] ?? false),
        ]);

        return $this->show($workspace);
    }
}
