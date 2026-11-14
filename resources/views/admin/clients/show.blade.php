@extends('layouts.admin')
@section('title', $client->name)

{{-- One user's account (Doc 15 section 15.7): how to reach them, its sign-in checks and alerts, the businesses they own and are in, and support's actions on it. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.clients.index') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--card-bg)] border border-[var(--card-border)] text-[var(--text-muted)] hover:text-[var(--primary)] transition-all" title="{{ __('Back') }}">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="page-title text-2xl font-bold text-[var(--text-primary)]">{{ $client->name }}</h1>
                @include('admin.clients._status-badge', ['status' => $client->status])
            </div>
            <p class="page-subtitle text-[var(--text-muted)] mt-1">{{ __('User') }} #{{ $client->id }}</p>
        </div>
    </div>

    @include('admin.clients._actions')
    @include('admin.clients._details')
    @include('admin.clients._security')
    @include('admin.clients._consents')
    @include('admin.clients._memberships')
    @include('admin.clients._totals')
    @include('admin.clients._businesses')

    <p class="text-xs mt-3 mb-6 text-[var(--text-muted)]">
        {{ __('Moving shows what changes first. Moving down turns off what the new plan does not include - a Full app falls back to its Lite edition where the plan has it - and keeps every record; over a user or location limit, the owner chooses what stays active. Moving up turns nothing on: the new apps become available in the workspace\'s Apps Center.') }}
    </p>

    @include('admin.partials.admin-activity', ['about' => __('this account')])
    @include('admin.clients._modals')
@endsection
