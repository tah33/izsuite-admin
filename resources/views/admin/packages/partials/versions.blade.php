{{--
    A plan's versions (Doc 15 section 15.8 - Phase E3b): its terms from each day, newest first, with what
    changed from the one before in bold. As a version starts, subscribers stay on the version they are on;
    earlier versions stay listed for the businesses still on them. The one waiting for its day can be removed.
    Expects $package, $versions and $may.
--}}
@php
    $states = [
        'scheduled' => ['badge-warning', __('Waiting')],
        'current'   => ['badge-success', __('Current')],
        'earlier'   => ['badge-inactive', __('Earlier')],
    ];
    $levels = [\App\Models\Billing\Package::REPORTING_LEVELS, \App\Models\Billing\Package::SUPPORT_LEVELS];
    // Each term as a line, by its column.
    $lines  = fn ($version) => array_filter([
        'user_limit'       => $version->user_limit === null ? __('Unlimited users') : trans_choice(':count user|:count users', $version->user_limit),
        'location_limit'   => $version->location_limit === null ? __('Unlimited locations') : trans_choice(':count location|:count locations', $version->location_limit),
        'business_limit'   => $version->business_limit === null ? __('Unlimited businesses per account') : trans_choice(':count business per account|:count businesses per account', $version->business_limit),
        'storage_limit_mb' => $version->storage_limit_mb === null ? __('Unlimited storage') : __(':size of storage', ['size' => \App\Support\Bytes::format($version->storage_limit_mb * 1048576)]),
        'removes_branding' => $version->removes_branding ? __('No "Powered by izSuite"') : __('"Powered by izSuite" on documents'),
        'reporting_level'  => $version->reporting_level ? __(':level reports', ['level' => $levels[0][$version->reporting_level] ?? $version->reporting_level]) : null,
        'support_level'    => $version->support_level ? __(':level support', ['level' => $levels[1][$version->support_level] ?? $version->support_level]) : null,
        'trial_days'       => $version->trial_days ? __(':days-day trial', ['days' => $version->trial_days]) : __('No trial'),
        'grace_days'       => $version->grace_days ? trans_choice(':count day of grace|:count days of grace', $version->grace_days) : __('No grace'),
    ]);
@endphp
<div class="card overflow-hidden mt-6" id="versions">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Versions') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ __('The plan\'s limits, branding, levels, trial and grace from each day. As a version starts, subscribers stay on the version they are on; businesses without a subscription move to it. Change them above.') }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Version') }}</th>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('Terms') }}</th>
                    <th>{{ __('Businesses') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($versions as $row)
                    @php
                        $version = $row['version'];
                        $before  = $versions[$loop->index + 1]['version'] ?? null;
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap">
                            <span class="text-sm font-semibold text-[var(--text-primary)]">{{ __('Version :number', ['number' => $version->number]) }}</span>
                            <span class="badge {{ $states[$row['state']][0] }} ms-1">{{ $states[$row['state']][1] }}</span>
                        </td>
                        <td class="text-sm whitespace-nowrap">
                            {{ $version->starts_on->format('M d, Y') }}
                            @if($version->creator)
                                <span class="block text-xs text-[var(--text-muted)]">{{ $version->creator->name }}</span>
                            @endif
                        </td>
                        <td class="text-sm min-w-[280px]">
                            @foreach($lines($version) as $term => $line)
                                <span class="{{ $before && $before->{$term} !== $version->{$term} ? 'font-semibold text-[var(--text-primary)]' : 'text-[var(--text-secondary)]' }}">{{ $line }}</span>@unless($loop->last)<span class="text-[var(--text-muted)]"> · </span>@endunless
                            @endforeach
                        </td>
                        <td class="text-sm whitespace-nowrap">
                            {{ $row['state'] === 'scheduled' ? '—' : trans_choice(':count business|:count businesses', $row['businesses']) }}
                        </td>
                        <td class="text-end whitespace-nowrap">
                            @if($row['state'] === 'scheduled' && $may('admin.packages.version-remove'))
                                <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Remove') }}" onclick="openConfirmModal('remove-version-{{ $version->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                <x-confirm-modal
                                    id="remove-version-{{ $version->id }}"
                                    :title="__('Remove This Version?')"
                                    :message="__('Version :number will not start on :date; the plan\'s terms stay as they are.', ['number' => $version->number, 'date' => $version->starts_on->format('M d, Y')])"
                                    :action="route('admin.packages.version-remove', [$package->id, $version->id])"
                                    method="DELETE"
                                    :confirm-text="__('Remove')"
                                    confirm-class="btn-danger"
                                    icon="trash-2"
                                />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
