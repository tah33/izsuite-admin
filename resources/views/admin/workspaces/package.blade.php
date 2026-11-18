{{-- Moving a business to another plan, seen before it is done (Doc 13
     sections 13 and 14): the apps the new plan turns off or back to Lite, and
     the business's users, locations and storage against its limits. --}}
@extends('layouts.admin')
@section('title', __('Move to :plan', ['plan' => $package->name]) . ' — ' . $workspace->name)

@php
    $from  = $workspace->package?->name ?? __('no plan');
    $limit = fn (?int $limit, string $one, string $many) => $limit === null ? __('no limit') : trans_choice(":count {$one}|:count {$many}", $limit);
    // A count limit matters once it is passed; storage once it is reached, as uploads then stop.
    $rows  = [
        ['label' => __('Users'), 'state' => $impact['users']['state'] === 'over' ? 'over' : null,
            'used' => trans_choice(':count user|:count users', $impact['users']['used']), 'limit' => $limit($impact['users']['limit'], 'user', 'users'),
            'note' => __('The owner chooses who stays active, in Settings › Apps & Subscription; the others are suspended. Until then nobody is switched off, and no one can be added.')],
        ['label' => __('Locations'), 'state' => $impact['locations']['state'] === 'over' ? 'over' : null,
            'used' => trans_choice(':count active location|:count active locations', $impact['locations']['used']), 'limit' => $limit($impact['locations']['limit'], 'location', 'locations'),
            'note' => __('The owner chooses which stay active; the others become inactive, their records kept. Until then none is switched off, and none can be added.')],
        ['label' => __('Storage'), 'state' => in_array($impact['storage']['state'], ['over', 'full'], true) ? $impact['storage']['state'] : null,
            'used' => \App\Support\Bytes::format($impact['storage']['used']), 'limit' => $impact['storage']['limit'] === null ? __('no limit') : \App\Support\Bytes::format($impact['storage']['limit']),
            'note' => __('Every file stays viewable and downloadable; uploads stop until files are deleted or the plan has room.')],
    ];
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.businesses.index') }}" class="text-[var(--primary)]">{{ __('Businesses') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="{{ route('admin.businesses.show', $workspace->id) }}" class="text-[var(--primary)]">{{ $workspace->name }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __('Move plan') }}</span>
        </div>
        <h1 class="page-title">{{ __('Move ":business" to :plan', ['business' => $workspace->name, 'plan' => $package->name]) }}</h1>
        <p class="page-subtitle">
            @if($impact['direction'] === 'same')
                {{ __('It is on :plan already.', ['plan' => $package->name]) }}
            @elseif($impact['direction'] === 'up')
                {{ __('From :from up to :to. Nothing is turned off: the new apps become available in its Apps Center.', ['from' => $from, 'to' => $package->name]) }}
            @else
                {{ __('From :from down to :to. Check what changes before moving it.', ['from' => $from, 'to' => $package->name]) }}
            @endif
        </p>
    </div>

    <div class="card mb-6">
        <h2 class="font-semibold text-[var(--text-primary)] mb-1">{{ __('Apps') }}</h2>
        @if($impact['apps'])
            <p class="text-sm text-[var(--text-muted)] mb-3">{{ __(':plan does not include these. Their records are kept and stay readable.', ['plan' => $package->name]) }}</p>
            <ul class="text-sm space-y-2">
                @foreach($impact['apps'] as $app)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-[var(--text-primary)]">{{ $app['name'] }}</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-[var(--text-muted)]"></i>
                        @if($app['lite'])
                            <span class="badge badge-trial">{{ $app['lite'] }}</span>
                        @else
                            <span class="badge badge-inactive">{{ __('Turned off') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-[var(--text-muted)]">{{ __('Every app it has on stays on: :plan includes them all.', ['plan' => $package->name]) }}</p>
        @endif
    </div>

    <div class="card mb-6">
        <h2 class="font-semibold text-[var(--text-primary)] mb-3">{{ __('Limits on :plan', ['plan' => $package->name]) }}</h2>
        <dl class="text-sm space-y-3">
            @foreach($rows as $row)
                <div class="flex flex-col sm:flex-row gap-2">
                    <dt class="w-36 shrink-0 text-[var(--text-muted)]">{{ $row['label'] }}</dt>
                    <dd class="flex-1 min-w-0">
                        <span class="inline-flex flex-wrap items-center gap-2">
                            <span class="text-[var(--text-primary)]">{{ $row['used'] }} · {{ __('the plan gives') }} {{ $row['limit'] }}</span>
                            @if($row['state'])
                                <span class="badge badge-danger">{{ $row['state'] === 'over' ? __('Over the limit') : __('Full') }}</span>
                            @else
                                <span class="badge badge-success">{{ __('Within') }}</span>
                            @endif
                        </span>
                        @if($row['state'])
                            <span class="block text-xs mt-1 text-[var(--text-muted)]">{{ $row['note'] }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if($impact['direction'] !== 'same')
            <form action="{{ route('admin.workspaces.package', $workspace->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="package_id" value="{{ $package->id }}">
                <button type="submit" class="btn btn-primary" data-loading-label="{{ __('Moving...') }}">
                    <i data-lucide="arrow-right-left" class="w-4 h-4"></i> {{ __('Move to :plan', ['plan' => $package->name]) }}
                </button>
            </form>
        @endif
        <a href="{{ route('admin.businesses.show', $workspace->id) }}" class="btn btn-secondary">{{ $impact['direction'] === 'same' ? __('Back') : __('Cancel') }}</a>
    </div>
@endsection
