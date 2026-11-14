@extends('layouts.admin')
@section('title', __('Feature Flags'))

{{-- Doc 15 section 15.12: features the code asks about by key, on for an audience between days, with an emergency off switch. --}}
@php
    $role   = auth()->user()->role;
    $may    = fn (string $route) => (bool) $role?->hasPermission($route);
    $now    = now();
    $badges = ['off' => 'badge-inactive', 'scheduled' => 'badge-warning', 'live' => 'badge-success', 'ended' => 'badge-inactive'];
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex-1 min-w-0">
            <h1 class="page-title">{{ __('Feature Flags') }}</h1>
            <p class="page-subtitle">{{ __('A feature on for the businesses chosen, and a share of those in the countries and plans chosen, between its days. Switching one off counts at once and removes nothing made with it.') }}</p>
        </div>
        @if($may('admin.feature-flags.create'))
            <a href="{{ route('admin.feature-flags.create') }}" class="btn btn-primary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Add Flag') }}</a>
        @endif
    </div>

    <form action="{{ route('admin.feature-flags.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name or key...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-36 shrink-0">
            <select name="status" class="form-select" aria-label="{{ __('Switch') }}">
                <option value="">{{ __('On or off') }}</option>
                <option value="on" @selected(request('status') === 'on')>{{ __('On') }}</option>
                <option value="off" @selected(request('status') === 'off')>{{ __('Off') }}</option>
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.feature-flags.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    @error('flag')
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $message }}</div>
    @enderror

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Flag') }}</th>
                        <th>{{ __('State') }}</th>
                        <th>{{ __('Audience') }}</th>
                        <th>{{ __('Days') }}</th>
                        <th>{{ __('Responsible') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flags as $flag)
                        @include('admin.feature-flags._row')
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="flag" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No feature flags yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($flags->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $flags->links() }}</div>
        @endif
    </div>
@endsection
