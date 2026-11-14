@extends('layouts.admin')
@section('title', __('Legal Hold'))

{{-- One legal hold (Doc 15 section 15.24): on what, why, by whom, and its release. Expects $hold. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.legal-holds.index') }}" class="text-[var(--primary)]">{{ __('Legal Holds') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $hold->business_name }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ __('Legal Hold on :business', ['business' => $hold->business_name]) }}</h1>
            <span class="badge {{ $hold->isActive() ? 'badge-danger' : 'badge-inactive' }}">{{ $hold->isActive() ? __('In force') : __('Released') }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card lg:col-span-2">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                @foreach([
                    __('Business')  => $hold->business_name.' · '.($hold->workspace_id ? sprintf('BW-%06d', $hold->workspace_id) : __('deleted')),
                    __('Reference') => $hold->reference ?? '—',
                    __('Placed')    => $hold->placed_at->format('M d, Y H:i').' · '.($hold->placer?->name ?? '-'),
                    __('Released')  => $hold->released_at ? $hold->released_at->format('M d, Y H:i').' · '.($hold->releaser?->name ?? '-') : __('Not yet'),
                ] as $label => $value)
                    <div>
                        <dt class="text-[var(--text-muted)]">{{ $label }}</dt>
                        <dd class="font-medium text-[var(--text-primary)]">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <h2 class="section-title mt-6 mb-2">{{ __('Why') }}</h2>
            <p class="text-sm whitespace-pre-line text-[var(--text-secondary)]">{{ $hold->reason }}</p>
            @if($hold->release_reason)
                <h2 class="section-title mt-6 mb-2">{{ __('Why It Was Released') }}</h2>
                <p class="text-sm whitespace-pre-line text-[var(--text-secondary)]">{{ $hold->release_reason }}</p>
            @endif
            @if($hold->workspace_id && $may('admin.businesses.show'))
                <a href="{{ route('admin.businesses.show', $hold->workspace_id) }}" class="btn btn-xs btn-secondary mt-4"><i data-lucide="building-2" class="w-3.5 h-3.5"></i>{{ __('The Business') }}</a>
            @endif
        </div>

        @if($hold->isActive() && $may('admin.legal-holds.release'))
            <form action="{{ route('admin.legal-holds.release', $hold->id) }}" method="POST" class="card">
                @csrf
                <h2 class="section-title mb-2">{{ __('Release It') }}</h2>
                <p class="text-sm mb-3 text-[var(--text-secondary)]">{{ __('Once no hold is in force, the business\'s records can be deleted again.') }}</p>
                @include('admin.partials.critical-confirm', ['impact' => __('Releasing the hold lets :business\'s records be deleted.', ['business' => $hold->business_name]), 'compact' => true])
                <button type="submit" class="btn btn-danger w-full mt-4"><i data-lucide="lock-keyhole-open" class="w-4 h-4"></i>{{ __('Release the Hold') }}</button>
            </form>
        @endif
    </div>
@endsection
