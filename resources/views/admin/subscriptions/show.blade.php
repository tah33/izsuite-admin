@extends('layouts.admin')
@section('title', $subscription->workspace?->name)

{{-- One business's subscription (Doc 15 section 15.13): where it stands, its invoices and card tries, izSuite's changes to it, and the admin activity on the business. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.subscriptions.index') }}" class="text-[var(--primary)]">{{ __('Subscriptions') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $subscription->workspace?->name }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $subscription->workspace?->name }}</h1>
            @include('admin.subscriptions._status-badge')
        </div>
        <p class="page-subtitle">{{ $subscription->package?->name }}, {{ __($subscription->cycle) }} · {{ $subscription->workspace?->businessId() }}</p>
    </div>

    @include('admin.subscriptions._actions')
    @include('admin.subscriptions._summary')
    @include('admin.subscriptions._invoices')
    @include('admin.partials.admin-activity', ['about' => __('this business')])
    @include('admin.subscriptions._modals')
@endsection
