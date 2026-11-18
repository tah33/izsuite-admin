@extends('layouts.admin')
@section('title', __('Usage').' · '.$business->name)

{{-- Doc 15 section 15.18: one business's usage now, against its plan, and its history from the daily snapshots. --}}
@php
    $role  = auth()->user()->role;
    $may   = fn (string $route) => (bool) $role?->hasPermission($route);
    $count = fn ($amount) => number_format((int) $amount);
    $bytes = fn ($amount) => \App\Support\Bytes::format((int) $amount);
    $cards = [
        [__('Users'), 'users', $usage['users'], $count, __('Active or invited, the owner too')],
        [__('Locations'), 'map-pin', $usage['locations'], $count, __('Active ones')],
        [__('Storage'), 'hard-drive', $usage['storage'], $bytes, __('Its files')],
    ];
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.usage.index') }}" class="text-[var(--primary)]">{{ __('Usage') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $business->name }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $business->name }}</h1>
            @include('admin.usage._state', ['state' => $usage['state']])
            @if($may('admin.businesses.show'))
                <a href="{{ route('admin.businesses.show', $business->id) }}" class="btn btn-secondary ms-auto">
                    <i data-lucide="building-2" class="w-4 h-4"></i>{{ __('Business') }}
                </a>
            @endif
        </div>
        <p class="page-subtitle">
            {{ $business->businessId() }} · {{ $business->plan_name ?? __('No plan') }}{{ $business->businessSetting?->country ? ' · '.(\App\Support\Countries::name($business->businessSetting->country) ?? $business->businessSetting->country) : '' }}
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach($cards as [$label, $icon, $line, $format, $hint])
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ $label }}</h3>
                    <i data-lucide="{{ $icon }}" class="w-4 h-4 text-[var(--text-muted)]"></i>
                </div>
                @include('admin.usage._cell', ['line' => $line, 'format' => $format])
                <p class="text-xs mt-2 text-[var(--text-muted)]">{{ $hint }} · {{ __(\App\Support\UsageState::LABELS[$line['state']]) }}</p>
            </div>
        @endforeach
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ __('Apps on') }}</h3>
                <i data-lucide="layout-grid" class="w-4 h-4 text-[var(--text-muted)]"></i>
            </div>
            <p class="text-sm text-[var(--text-primary)]">{{ $usage['apps']['used'] }}</p>
            <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __(':count on its plan', ['count' => $usage['apps']['plan']]) }}</p>
        </div>
    </div>

    @include('admin.usage._history')
@endsection

@if($chart['labels'] !== [])
    @push('scripts')
    <script>
    $(document).ready(function () {
        var chart = @json($chart);
        var css = getComputedStyle(document.documentElement);

        new Chart(document.getElementById('usage-history-chart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chart.labels,
                datasets: [
                    { label: '{{ __('Users') }}', data: chart.users, borderColor: css.getPropertyValue('--primary').trim() || '#1769E8', yAxisID: 'count', tension: 0.2, pointRadius: 2 },
                    { label: '{{ __('Locations') }}', data: chart.locations, borderColor: '#0E9F6E', yAxisID: 'count', tension: 0.2, pointRadius: 2 },
                    { label: '{{ __('Apps on') }}', data: chart.apps, borderColor: '#9061F9', yAxisID: 'count', tension: 0.2, pointRadius: 2 },
                    { label: '{{ __('Storage (MB)') }}', data: chart.storage, borderColor: '#F59E0B', borderDash: [5, 4], yAxisID: 'storage', tension: 0.2, pointRadius: 2 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: {
                    count: { position: 'left', beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.04)' } },
                    storage: { position: 'right', beginAtZero: true, grid: { display: false }, title: { display: true, text: 'MB' } },
                    x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } }
                }
            }
        });
    });
    </script>
    @endpush
@endif
