@extends('layouts.admin')
@section('title', __('Payments'))

{{-- Doc 15 section 14: every payment for izSuite's invoices - a card's, the saved card's tries, a transfer izSuite confirmed - the failed ones among them. Refunds are made from an invoice's page. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Payments') }}</h1>
        <p class="page-subtitle">{{ __('Every payment for izSuite\'s invoices, by card or bank transfer, and the card tries that did not go through. Refund one from its invoice.') }}</p>
    </div>

    <form action="{{ route('admin.payments.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business, BW- ID, owner, invoice or reference...') }}" class="form-input search-input w-full">
        </div>
        <div class="w-48 shrink-0">
            <select name="status" class="form-select" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Any status') }}</option>
                @foreach(['paid' => __('Paid'), 'failed' => __('Failed'), 'processing' => __('Processing'), 'not_applied' => __('Not applied'), 'refunded' => __('Refunded')] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40 shrink-0">
            <select name="method" class="form-select" aria-label="{{ __('Paid by') }}">
                <option value="">{{ __('Paid by any') }}</option>
                <option value="card" @selected(request('method') === 'card')>{{ __('Card') }}</option>
                <option value="transfer" @selected(request('method') === 'transfer')>{{ __('Bank transfer') }}</option>
            </select>
        </div>
        <x-per-page-select />
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Paid By') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        @include('admin.payments._row')
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="banknote" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No payments match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection
