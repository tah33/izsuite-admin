@extends('layouts.admin')
@section('title', __('Refunds'))

{{-- Doc 15 section 14: every refund of a payment for izSuite's invoices, with its reason. A card's counts once its provider confirms it. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Refunds') }}</h1>
        <p class="page-subtitle">{{ __('Money izSuite gave back, with why and by whom. A card refund counts once the card provider confirms it; a bank transfer\'s is recorded once izSuite has sent it.') }}</p>
    </div>

    <form action="{{ route('admin.refunds.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business, BW- ID, owner, invoice or reference...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-52 shrink-0">
            <select name="status" class="form-select" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Any status') }}</option>
                @foreach(['succeeded' => __('Refunded'), 'pending' => __('Waiting for the provider'), 'failed' => __('Failed')] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.refunds.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refunds as $refund)
                        @include('admin.refunds._row')
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="undo-2" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No refunds match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($refunds->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $refunds->links() }}</div>
        @endif
    </div>
@endsection
