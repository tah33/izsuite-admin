@extends('layouts.admin')
@section('title', __('Credits'))

{{-- Doc 15 sections 15.13 and 15.14: izSuite's credit to businesses, given from a subscription's page and taken off their next invoices before tax. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Credits') }}</h1>
        <p class="page-subtitle">{{ __('izSuite\'s credit to businesses: each one given or taken back, with why, and what their invoices took off before tax or gave back when cancelled.') }}</p>
    </div>

    <form action="{{ route('admin.credits.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business, BW- ID, owner or invoice...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-52 shrink-0">
            <select name="kind" class="form-select" aria-label="{{ __('Movement') }}">
                <option value="">{{ __('Every movement') }}</option>
                @foreach(['granted' => __('Given'), 'used' => __('Taken off an invoice'), 'returned' => __('Given back'), 'removed' => __('Taken back')] as $value => $label)
                    <option value="{{ $value }}" @selected(request('kind') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.credits.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Movement') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($credits as $credit)
                        @include('admin.credits._row')
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="gift" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No credits match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($credits->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $credits->links() }}</div>
        @endif
    </div>
@endsection
