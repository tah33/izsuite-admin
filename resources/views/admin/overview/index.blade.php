@extends('layouts.admin')
@section('title', __('Overview'))

{{-- Doc 15 section 15.4: izSuite at a glance - its businesses as they are now, and the chosen days' sign-ups, use and billing. Sums and counts only, never a business's own transactions. --}}
@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Overview') }}</h1>
        <p class="page-subtitle">{{ __('izSuite at a glance: its businesses as they are now, and the chosen days\' sign-ups, use and billing.') }}</p>
    </div>

    @if($alerts !== null)
        @include('admin.overview._alerts')
    @endif

    @include('admin.overview._filters')
    @include('admin.overview._businesses')
    @include('admin.overview._period')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        @include('admin.overview._apps')
        @include('admin.overview._operations')
    </div>

    @if($billing !== null)
        @include('admin.overview._billing')
    @endif
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    var chart = @json($period['chart']);

    new Chart(document.getElementById('new-businesses-chart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: chart.labels,
            datasets: [{
                label: '{{ __('New businesses') }}',
                data: chart.values,
                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#1769E8',
                borderRadius: 4,
                maxBarThickness: 36,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } }
            }
        }
    });
});
</script>
@endpush
