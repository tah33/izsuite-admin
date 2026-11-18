{{-- The chosen days (Doc 15 section 15.4): new businesses and accounts, active users, and new businesses by day or month. --}}
@php
    $days = \Illuminate\Support\Carbon::parse($filters['from'])->format('M j, Y').' – '.\Illuminate\Support\Carbon::parse($filters['to'])->format('M j, Y');
@endphp
<h3 class="section-title mb-3">{{ $days }}</h3>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    @include('components.stat-card', ['label' => __('New businesses'), 'value' => number_format($period['new_businesses']), 'icon' => 'building', 'iconColor' => 'blue', 'stagger' => 'stagger-1'])
    @include('components.stat-card', ['label' => __('New accounts'), 'value' => number_format($period['new_accounts']), 'icon' => 'user-plus', 'iconColor' => 'blue', 'stagger' => 'stagger-2'])
    @include('components.stat-card', ['label' => __('Active users'), 'value' => number_format($period['active_users']), 'icon' => 'user-check', 'iconColor' => 'green', 'stagger' => 'stagger-3'])
</div>
<p class="text-xs -mt-2 mb-4 text-[var(--text-muted)]">
    {{ __('New accounts are those made on the website: an owner\'s at sign-up, or an invited user\'s. Active users signed in, used the app or changed a record in these days.') }}
</p>

<div class="card mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="section-title">{{ __('New Businesses') }}</h3>
        <span class="text-sm text-[var(--text-muted)]">{{ $period['chart']['by_month'] ? __('By month') : __('By day') }}</span>
    </div>
    <div class="h-[240px] relative w-full overflow-hidden">
        <canvas id="new-businesses-chart" aria-label="{{ __('New businesses') }}" role="img"></canvas>
    </div>
</div>
