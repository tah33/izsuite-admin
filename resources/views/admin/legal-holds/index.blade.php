@extends('layouts.admin')
@section('title', __('Legal Holds'))

{{-- Doc 15 section 15.24: "legal holds prevent affected records from being permanently deleted". --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Legal Holds') }}</h1>
            <p class="page-subtitle">{{ __('While a hold is in force on a business, its owner cannot delete it and nobody in it can delete its records. Closing it, which keeps everything, still can.') }}</p>
        </div>
        @if($may('admin.legal-holds.create'))
            <a href="{{ route('admin.legal-holds.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Place a Hold') }}</a>
        @endif
    </div>

    <form action="{{ route('admin.legal-holds.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business, BW- ID or reference...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-40 shrink-0">
            <select name="state" class="form-select" aria-label="{{ __('State') }}">
                <option value="">{{ __('Any') }}</option>
                <option value="active" @selected(request('state') === 'active')>{{ __('In force') }}</option>
                <option value="released" @selected(request('state') === 'released')>{{ __('Released') }}</option>
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.legal-holds.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Placed') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($holds as $hold)
                        <tr>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]">{{ $hold->business_name }}</span>
                                <span class="block text-xs text-[var(--text-muted)]">{{ $hold->workspace_id ? sprintf('BW-%06d', $hold->workspace_id) : __('Business deleted') }}</span>
                            </td>
                            <td class="text-sm">{{ $hold->reference ?? '—' }}</td>
                            <td>
                                @if($hold->isActive())
                                    <span class="badge badge-danger">{{ __('In force') }}</span>
                                @else
                                    <span class="badge badge-inactive">{{ __('Released') }}</span>
                                    <span class="block text-xs mt-1 text-[var(--text-muted)]">{{ $hold->released_at->format('M d, Y') }}</span>
                                @endif
                            </td>
                            <td class="text-sm whitespace-nowrap">{{ $hold->placed_at->format('M d, Y') }}<span class="block text-xs text-[var(--text-muted)]">{{ $hold->placer?->name }}</span></td>
                            <td class="text-end">
                                @if($may('admin.legal-holds.show'))
                                    <a href="{{ route('admin.legal-holds.show', $hold->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Open') }}"><i data-lucide="eye" class="w-3.5 h-3.5"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="lock-keyhole" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No legal holds here.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($holds->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $holds->links() }}</div>
        @endif
    </div>
@endsection
