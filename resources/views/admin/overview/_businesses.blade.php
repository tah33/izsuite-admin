{{-- The businesses as they are now (Doc 15 section 15.4): by status, subscription and plan - the days do not change these. --}}
<h3 class="section-title mb-3">{{ __('Businesses Now') }}</h3>

<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-4">
    @include('components.stat-card', ['label' => __('All businesses'), 'value' => number_format($businesses['all']), 'icon' => 'building-2', 'iconColor' => 'blue', 'stagger' => 'stagger-1'])
    @include('components.stat-card', ['label' => __('Active'), 'value' => number_format($businesses['active']), 'icon' => 'circle-check', 'iconColor' => 'green', 'stagger' => 'stagger-2'])
    @include('components.stat-card', ['label' => __('On a trial'), 'value' => number_format($businesses['trialing']), 'icon' => 'hourglass', 'iconColor' => 'blue', 'stagger' => 'stagger-3'])
    @include('components.stat-card', ['label' => __('Past due'), 'value' => number_format($businesses['past_due']), 'icon' => 'credit-card', 'iconColor' => 'yellow', 'stagger' => 'stagger-4'])
    @include('components.stat-card', ['label' => __('In grace period'), 'value' => number_format($businesses['grace']), 'icon' => 'timer', 'iconColor' => 'yellow', 'stagger' => 'stagger-4'])
    @include('components.stat-card', ['label' => __('Suspended'), 'value' => number_format($businesses['suspended']), 'icon' => 'ban', 'iconColor' => 'red', 'stagger' => 'stagger-4'])
</div>

<div class="card mb-6">
    <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
        <span class="text-sm font-semibold text-[var(--text-primary)]">{{ __('On each plan') }}</span>
        @foreach($businesses['plans'] as $plan)
            <span class="text-sm text-[var(--text-secondary)]">
                {{ $plan['name'] }} <strong class="ms-1 text-base text-[var(--text-primary)]">{{ number_format($plan['count']) }}</strong>
            </span>
        @endforeach
        @if(auth()->user()->role?->hasPermission('admin.businesses.index'))
            <a href="{{ route('admin.businesses.index', array_filter(['package' => $filters['package'] ?? null, 'subscription' => $filters['subscription'] ?? null, 'country' => $filters['country'] ?? null])) }}" class="ms-auto text-sm font-medium text-[var(--primary)]">{{ __('Open Businesses') }}</a>
        @endif
    </div>
</div>
