@extends('layouts.admin')
@section('title', __('Subscription Invoices'))

{{-- izSuite's invoices to businesses for their subscriptions (Doc 13 Phase C1),
     the ones waiting for a bank transfer first. Confirming a payment issues
     the receipt and starts the plan. Each opens its own page (Doc 15 Phase C2a). --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.subscription-invoices.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Number, business or transfer reference...') }}" class="form-input search-input w-full">
            </div>
            <div class="w-44 shrink-0">
                <select name="status" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All invoices') }}</option>
                    <option value="open" @selected(request('status') === 'open')>{{ __('To be paid') }}</option>
                    <option value="paid" @selected(request('status') === 'paid')>{{ __('Paid') }}</option>
                    <option value="void" @selected(request('status') === 'void')>{{ __('Cancelled') }}</option>
                </select>
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
            <a href="{{ route('admin.subscription-invoices.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
        </form>
    </div>

    @error('invoice')
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $message }}</div>
    @enderror

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @include('admin.subscription-invoices._row')
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="receipt" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No subscription invoices yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection
