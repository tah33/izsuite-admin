{{--
    The versions of a country's defaults (Doc 15 section 15.19 - Phase F3a): each from its day, newest first, with what
    changed from the one before in bold. The one waiting for its day can be removed. Expects $country, $versions and $may.
--}}
@php
    $states  = [
        'scheduled' => ['badge-warning', __('Waiting')],
        'current'   => ['badge-success', __('Current')],
        'earlier'   => ['badge-inactive', __('Earlier')],
    ];
    $numbers = \App\Models\User\DocumentTemplate::NUMBER_FORMAT_EXAMPLES;
    // Each default as a line, by its column.
    $lines   = fn ($version) => [
        'currency'                => $version->currency ?? __('No currency'),
        'timezone'                => $version->timezone ?? __('No time zone'),
        'date_format'             => now()->format($version->date_format),
        'time_format'             => $version->time_format === '12h' ? __('12-hour') : __('24-hour'),
        'number_format'           => $numbers[$version->number_format] ?? $version->number_format,
        'week_start'              => __('Weeks from :day', ['day' => ucfirst($version->week_start)]),
        'paper_size'              => $version->paper_size === 'letter' ? __('Letter') : 'A4',
        'fiscal_year_start_month' => __('Fiscal year from :month', ['month' => \Carbon\Carbon::create(2026, $version->fiscal_year_start_month, 1)->format('F')]),
        'region_label'            => $version->region_label,
        'postal_label'            => $version->postal_label,
        'address_format'          => implode(' / ', explode("\n", $version->address_format)),
    ];
@endphp
<div class="card overflow-hidden mt-6" id="versions">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Versions') }}</h2>
        <p class="text-sm text-[var(--text-muted)]">{{ __('The country\'s defaults from each day. Change them above.') }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Version') }}</th>
                    <th>{{ __('From') }}</th>
                    <th>{{ __('Defaults') }}</th>
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
                        <td class="text-sm min-w-[320px]">
                            @foreach($lines($version) as $field => $line)
                                <span class="{{ $before && $before->{$field} !== $version->{$field} ? 'font-semibold text-[var(--text-primary)]' : 'text-[var(--text-secondary)]' }}">{{ $line }}</span>@unless($loop->last)<span class="text-[var(--text-muted)]"> · </span>@endunless
                            @endforeach
                        </td>
                        <td class="text-end whitespace-nowrap">
                            @if($row['state'] === 'scheduled' && $may('admin.countries.version-remove'))
                                <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Remove') }}" onclick="openConfirmModal('remove-version-{{ $version->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                <x-confirm-modal
                                    id="remove-version-{{ $version->id }}"
                                    :title="__('Remove This Version?')"
                                    :message="__('Version :number will not start on :date; the country\'s defaults stay as they are.', ['number' => $version->number, 'date' => $version->starts_on->format('M d, Y')])"
                                    :action="route('admin.countries.version-remove', [$country->id, $version->id])"
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
