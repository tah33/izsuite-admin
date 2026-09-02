@extends('layouts.admin')
@section('title', __('Admin Overview'))

@section('content')

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        @include('components.stat-card', ['label' => __('Total Users'), 'value' => number_format($stats['total_users']), 'icon' => 'users', 'iconColor' => 'blue', 'stagger' => 'stagger-1'])
        @include('components.stat-card', ['label' => __('Active Users'), 'value' => number_format($stats['active_users']), 'icon' => 'user-check', 'iconColor' => 'blue', 'stagger' => 'stagger-2'])
        @include('components.stat-card', ['label' => __('Pro Users'), 'value' => number_format($stats['pro_subscribers']), 'icon' => 'crown', 'iconColor' => 'yellow', 'stagger' => 'stagger-3'])
        @include('components.stat-card', ['label' => __('Monthly Revenue'), 'value' => '$' . number_format($stats['mrr'], 2), 'icon' => 'trending-up', 'iconColor' => 'blue', 'stagger' => 'stagger-4'])
        @include('components.stat-card', ['label' => __('Pending Messages'), 'value' => number_format($stats['new_messages']), 'icon' => 'mail-plus', 'iconColor' => 'orange', 'stagger' => 'stagger-5'])
    </div>

    <div class="card mb-3">
        <div class="flex items-center justify-between mb-4">
            <h3 class="section-title">{{ __('User Growth') }}</h3>
            <span class="text-sm text-[var(--text-muted)]">{{ __('Last 6 months') }}</span>
        </div>
        <div class="h-[280px] relative w-full">
            <canvas id="growth-chart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <a href="{{ route('admin.staff.index') }}" class="card flex items-center gap-4 hover:border-transparent transition-all duration-300">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-[var(--info-light)] text-[var(--info)]">
                <i data-lucide="user-check" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="font-semibold text-sm text-[var(--text-primary)]">{{ __('Staff') }}</p>
                <p class="text-xs text-[var(--text-muted)]">{{ __('Team') }}</p>
            </div>
        </a>

        <a href="{{ route('admin.plans.index') }}" class="card flex items-center gap-4 hover:border-transparent transition-all duration-300">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-[var(--sidebar-active-bg)] text-[var(--primary)]">
                <i data-lucide="tag" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="font-semibold text-sm text-[var(--text-primary)]">{{ __('Plans') }}</p>
                <p class="text-xs text-[var(--text-muted)]">{{ __('Pricing') }}</p>
            </div>
        </a>

        <a href="{{ route('admin.contact-messages.index') }}" class="card flex items-center gap-4 hover:border-transparent transition-all duration-300">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-[var(--warning-light)] text-[var(--warning)]">
                <i data-lucide="mail" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="font-semibold text-sm text-[var(--text-primary)]">{{ __('Contact Inbox') }}</p>
                <p class="text-xs text-[var(--text-muted)]">{{ __('Messages') }}</p>
            </div>
        </a>

        <a href="{{ route('admin.tickets.index') }}" class="card flex items-center gap-4 hover:border-transparent transition-all duration-300">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-[var(--bg-secondary)] text-[var(--text-muted)]">
                <i data-lucide="ticket" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="font-semibold text-sm text-[var(--text-primary)]">{{ __('Tickets') }}</p>
                <p class="text-xs text-[var(--text-muted)]">{{ __('Support') }}</p>
            </div>
        </a>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    var ctx = document.getElementById('growth-chart').getContext('2d');
    var chartData = @json($chartData);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.map(function(d) { return d.label; }),
            datasets: [{
                label: '{{ __("New Users") }}',
                data: chartData.map(function(d) { return d.value; }),
                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#2563EB',
                borderRadius: 6,
                barThickness: 40,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: 'rgba(0,0,0,0.04)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
@endpush
