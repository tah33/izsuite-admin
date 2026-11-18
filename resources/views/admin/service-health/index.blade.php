@extends('layouts.admin')
@section('title', __('Service Health'))

{{-- Doc 15 section 15.23: the services izSuite runs on and uses, each asked afresh, with the failures behind them. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Service Health') }}</h1>
            <p class="page-subtitle">{{ __('Each service izSuite runs on or uses, asked afresh at :time UTC.', ['time' => $checkedAt->format('M d, Y H:i:s')]) }}</p>
        </div>
        <a href="{{ route('admin.service-health.index') }}" class="btn btn-secondary"><i data-lucide="refresh-cw" class="w-4 h-4"></i>{{ __('Check Again') }}</a>
    </div>

    @if($may('admin.incidents.index'))
        @include('admin.service-health._incidents')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        @foreach($statuses as $status)
            @include('admin.service-health._status', ['status' => $status])
        @endforeach
    </div>

    @include('admin.service-health._jobs')
    @include('admin.service-health._failures')
@endsection
