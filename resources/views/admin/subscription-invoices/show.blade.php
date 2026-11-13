@extends('layouts.admin')
@section('title', $invoice->number)

{{-- One of izSuite's invoices (Doc 15 section 14 - Phase C2a): every field the doc names, its payments with their refunds, and refunding one. --}}
@php
    $role  = auth()->user()->role;
    $may   = fn (string $route) => (bool) $role?->hasPermission($route);
    $badge = ['open' => ['badge-warning', __('To be paid')], 'paid' => ['badge-success', __('Paid')], 'void' => ['badge-inactive', __('Cancelled')]][$invoice->status] ?? ['badge-inactive', $invoice->status];
    $money = fn ($amount) => $invoice->currency.' '.number_format((float) $amount, 2);
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.subscription-invoices.index') }}" class="text-[var(--primary)]">{{ __('Subscription Invoices') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $invoice->number }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="page-title">{{ $invoice->number }}</h1>
            <span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span>
            @if(bccomp($refunded, '0', 2) > 0)
                <span class="badge badge-inactive">{{ __('Refunded :amount', ['amount' => $money($refunded)]) }}</span>
            @endif
        </div>
        <p class="page-subtitle">{{ $invoice->workspace?->name }} · {{ $invoice->package_name }}, {{ __($invoice->cycle) }} · {{ __(ucfirst($invoice->kind)) }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        @if($may('admin.subscription-invoices.pdf'))
            <a href="{{ route('admin.subscription-invoices.pdf', $invoice->id) }}" target="_blank" class="btn btn-secondary"><i data-lucide="file-text" class="w-4 h-4"></i>{{ __('Invoice PDF') }}</a>
            @if($invoice->receipt_number)
                <a href="{{ route('admin.subscription-invoices.pdf', ['id' => $invoice->id, 'receipt' => 1]) }}" target="_blank" class="btn btn-secondary"><i data-lucide="receipt" class="w-4 h-4"></i>{{ __('Receipt :number', ['number' => $invoice->receipt_number]) }}</a>
            @endif
        @endif
        @if($invoice->isOpen() && $may('admin.subscription-invoices.confirm'))
            <form action="{{ route('admin.subscription-invoices.confirm', $invoice->id) }}" method="POST" onsubmit="return confirm('{{ __('Confirm that :total arrived for :number? This issues the receipt and starts the plan.', ['total' => $money($invoice->total), 'number' => $invoice->number]) }}');">
                @csrf
                <button type="submit" class="btn btn-primary"><i data-lucide="check" class="w-4 h-4"></i>{{ __('Confirm Payment') }}</button>
            </form>
        @endif
        @if($invoice->isOpen() && $may('admin.subscription-invoices.void'))
            <form action="{{ route('admin.subscription-invoices.void', $invoice->id) }}" method="POST" onsubmit="return confirm('{{ __('Cancel :number? It stays in the history and will not be paid.', ['number' => $invoice->number]) }}');">
                @csrf
                <button type="submit" class="btn btn-secondary">{{ __('Cancel Invoice') }}</button>
            </form>
        @endif
        <div class="ms-auto flex flex-wrap gap-2">
            @if($invoice->workspace?->subscription && $may('admin.subscriptions.show'))
                <a href="{{ route('admin.subscriptions.show', $invoice->workspace->subscription->id) }}" class="btn btn-secondary"><i data-lucide="repeat" class="w-4 h-4"></i>{{ __('The Subscription') }}</a>
            @endif
            @if($invoice->workspace && $may('admin.businesses.show'))
                <a href="{{ route('admin.businesses.show', $invoice->workspace_id) }}" class="btn btn-secondary"><i data-lucide="building-2" class="w-4 h-4"></i>{{ __('The Business') }}</a>
            @endif
        </div>
    </div>

    @include('admin.subscription-invoices._summary')
    @include('admin.subscription-invoices._payments')

    @if($may('admin.payments.refund'))
        @foreach($invoice->payments as $payment)
            @if(bccomp($refundable[$payment->id] ?? '0', '0', 2) > 0)
                @include('admin.subscription-invoices._refund-modal', ['left' => $refundable[$payment->id]])
            @endif
        @endforeach
    @endif
@endsection
