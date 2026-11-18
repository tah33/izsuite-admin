@extends('layouts.admin')
@section('title', $business->name)

{{-- One business (Doc 15 section 15.5): who and what it is, its usage, its status (15.6) and the admin activity on it. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.businesses.index') }}" class="text-[var(--primary)]">{{ __('Businesses') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $business->name }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $business->name }}</h1>
            @include('admin.businesses._status-badge', ['status' => $business->status])
        </div>
        <p class="page-subtitle">{{ $business->businessId() }} · {{ __('Signed up :date', ['date' => $business->created_at->format('M d, Y')]) }}</p>
        @foreach($legalHolds as $hold)
            <p class="text-sm mt-2 text-[var(--danger)]"><i data-lucide="lock-keyhole" class="w-4 h-4 inline-block me-1"></i>{{ __('Legal hold since :date:reference: nothing of it can be deleted.', ['date' => $hold->placed_at->format('M d, Y'), 'reference' => $hold->reference ? ' ('.$hold->reference.')' : '']) }}</p>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        @if($may('admin.businesses.status'))
            <button type="button" class="btn btn-primary" data-modal-open="business-status-modal">
                <i data-lucide="shield" class="w-4 h-4"></i>{{ __('Change Status') }}
            </button>
        @endif
        @if($may('admin.businesses.notify'))
            <button type="button" class="btn btn-secondary" data-modal-open="business-notify-modal">
                <i data-lucide="send" class="w-4 h-4"></i>{{ __('Send a Message') }}
            </button>
        @endif
        @if($business->user && $may('admin.clients.show'))
            <a href="{{ route('admin.clients.show', $business->user_id) }}" class="btn btn-secondary">
                <i data-lucide="user" class="w-4 h-4"></i>{{ __('Owner\'s Account') }}
            </a>
        @endif
        @if($business->subscription && $may('admin.subscriptions.show'))
            <a href="{{ route('admin.subscriptions.show', $business->subscription->id) }}" class="btn btn-secondary">
                <i data-lucide="repeat" class="w-4 h-4"></i>{{ __('Subscription') }}
            </a>
        @endif
        @if($may('admin.businesses.support-session'))
            <button type="button" class="btn btn-secondary" data-modal-open="support-session-modal">
                <i data-lucide="life-buoy" class="w-4 h-4"></i>{{ __('Support Session') }}
            </button>
        @endif
        @if($may('admin.businesses.support-session-edit'))
            <button type="button" class="btn btn-secondary" data-modal-open="support-session-edit-modal">
                <i data-lucide="pencil" class="w-4 h-4"></i>{{ __('With Edit Access') }}
            </button>
        @endif
        @if($may('admin.workspaces.package.review'))
            <form action="{{ route('admin.workspaces.package.review', $business->id) }}" method="GET" class="flex items-center gap-2 ms-auto">
                <div class="w-40">
                    <select name="package_id" class="form-select" aria-label="{{ __('Move to plan') }}">
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}" @selected($business->package_id === $package->id)>{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">{{ __('Move Plan') }}</button>
            </form>
        @endif
    </div>

    @include('admin.businesses._overview')
    @include('admin.businesses._usage')
    @include('admin.businesses._status')
    @include('admin.businesses._cases')
    @include('admin.businesses._support-sessions')
    @include('admin.partials.admin-activity', ['about' => __('this business')])

    @if($may('admin.businesses.status'))
        @include('admin.businesses._status-modal')
    @endif
    @if($may('admin.businesses.notify'))
        @include('admin.businesses._notify-modal')
    @endif
    @if($may('admin.businesses.support-session'))
        @include('admin.businesses._support-session-modal', ['edit' => false])
    @endif
    @if($may('admin.businesses.support-session-edit'))
        @include('admin.businesses._support-session-modal', ['edit' => true])
    @endif
@endsection
