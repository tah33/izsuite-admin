<?php

namespace App\Services\Admin;

use App\Models\Admin\FeatureFlag;
use App\Models\User\User;
use App\Repositories\Admin\FeatureFlagRepository;
use App\Repositories\Admin\StaffRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\Features;
use App\Support\Countries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Feature Flags (Doc 15 section 15.12 - Phase E1): flags made,
 * changed, switched on or off at once - the emergency switch - and deleted
 * once off. Every change is in the audit log, and counts at once.
 */
class FeatureFlagAdminService
{
    public function __construct(
        protected FeatureFlagRepository $flags,
        protected StaffRepository $staff,
        protected PackageRepository $packages,
        protected Features $features,
    ) {}

    public function page(array $filters): LengthAwarePaginator
    {
        return $this->flags->page($filters, requested_per_page());
    }

    /** What the form offers: the flag when editing, with its businesses written out; the admins, plans and countries. */
    public function form(?int $id = null): array
    {
        $flag = $id ? $this->find($id) : null;

        return [
            'flag'       => $flag,
            'businesses' => $flag ? implode("\n", $this->flags->businessLabels($flag->businesses ?? [])) : '',
            'staff'      => $this->staff->active(),
            'plans'      => $this->packages->allByRank(),
            'countries'  => Countries::all(),
        ];
    }

    public function create(array $data, User $admin): FeatureFlag
    {
        $flag = $this->flags->create([...$this->clean($data), 'created_by' => $admin->id]);

        $this->features->forget();
        ActivityLogService::record('created', "Added feature flag {$flag->key}, ".$flag->status, $flag, null, $admin);

        return $flag;
    }

    public function update(int $id, array $data, User $admin): FeatureFlag
    {
        $flag = $this->flags->update($this->find($id), $this->clean($data));

        $this->features->forget();
        ActivityLogService::record('updated', "Changed feature flag {$flag->key}, ".$flag->status, $flag, null, $admin);

        return $flag;
    }

    /** Switches flag $id on or off at once - off, the emergency switch, overriding its audience and days. */
    public function switch(int $id, bool $on, User $admin): FeatureFlag
    {
        $flag = $this->flags->update($this->find($id), ['status' => $on ? FeatureFlag::ON : FeatureFlag::OFF]);

        $this->features->forget();
        ActivityLogService::record('updated', "Switched feature flag {$flag->key} ".$flag->status, $flag, null, $admin);

        return $flag;
    }

    /** Deleted - only once off. Nothing made while it was on goes with it. */
    public function delete(int $id, User $admin): void
    {
        $flag = $this->find($id);

        if ($flag->status === FeatureFlag::ON) {
            throw ValidationException::withMessages(['flag' => "Switch {$flag->key} off before deleting it."]);
        }

        $this->flags->delete($flag);

        $this->features->forget();
        ActivityLogService::record('deleted', "Deleted feature flag {$flag->key}", null, null, $admin);
    }

    private function find(int $id): FeatureFlag
    {
        return $this->flags->find($id) ?? abort(404);
    }

    /**
     * The businesses as written - BW- IDs or plain ones, a name after each
     * allowed, on lines or with commas - as IDs, each a business; lists left
     * empty as null; who is responsible, staff who may sign in.
     */
    private function clean(array $data): array
    {
        preg_match_all('/^\s*(?:BW-?)?0*(\d+)|,\s*(?:BW-?)?0*(\d+)/im', (string) ($data['businesses'] ?? ''), $found);
        $ids     = array_values(array_unique(array_map('intval', array_filter([...$found[1], ...$found[2]], 'strlen'))));
        $unknown = array_diff($ids, $this->flags->businessIds($ids));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['businesses' => 'No business has the ID '.sprintf('BW-%06d', reset($unknown)).'.']);
        }

        if (filled($data['responsible_id'] ?? null) && ! $this->staff->active()->contains('id', (int) $data['responsible_id'])) {
            throw ValidationException::withMessages(['responsible_id' => 'Choose an admin who may sign in.']);
        }

        return [
            ...$data,
            'businesses'     => $ids ?: null,
            'countries'      => ! empty($data['countries']) ? array_values($data['countries']) : null,
            'packages'       => ! empty($data['packages']) ? array_values($data['packages']) : null,
            'responsible_id' => filled($data['responsible_id'] ?? null) ? (int) $data['responsible_id'] : null,
        ];
    }
}
