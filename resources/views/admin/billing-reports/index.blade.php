@extends('layouts.admin')
@section('title', __('Billing Reports'))

{{-- Doc 15 section 14: izSuite's own billing summed for a span of months - revenue, open and overdue invoices, failed payments and credits. Never a business's own transactions; amounts never mix currencies. --}}
@php
    $role  = auth()->user()->role;
    $may   = fn (string $route) => (bool) $role?->hasPermission($route);
    $money = fn (?string $currency, $amount) => $currency.' '.number_format((float) $amount, 2);
    $month = fn (string $month) => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('M Y');
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Billing Reports') }}</h1>
        <p class="page-subtitle">{{ __(':from to :to. What izSuite collected and refunded, what is still owed, the card payments that failed, and its credit to businesses.', ['from' => $from->format('M Y'), 'to' => $to->format('M Y')]) }}</p>
    </div>

    <form action="{{ route('admin.billing-reports.index') }}" method="GET" class="flex flex-wrap items-end gap-2 mb-4">
        <div class="w-44 shrink-0">
            <label for="report-from" class="form-label text-xs">{{ __('From') }}</label>
            <input type="month" id="report-from" name="from" value="{{ request('from', $from->format('Y-m')) }}" class="form-input">
        </div>
        <div class="w-44 shrink-0">
            <label for="report-to" class="form-label text-xs">{{ __('To') }}</label>
            <input type="month" id="report-to" name="to" value="{{ request('to', $to->format('Y-m')) }}" class="form-input">
        </div>
        <div class="w-40 shrink-0">
            <select name="currency" class="form-select" aria-label="{{ __('Currency') }}">
                <option value="">{{ __('Every currency') }}</option>
                @foreach($currencies as $code)
                    <option value="{{ $code }}" @selected(request('currency') === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40 shrink-0">
            <select name="plan" class="form-select" aria-label="{{ __('Plan') }}">
                <option value="">{{ __('All plans') }}</option>
                @foreach($planNames as $name)
                    <option value="{{ $name }}" @selected(request('plan') === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Show') }}</button>
        <a href="{{ route('admin.billing-reports.index') }}" class="btn btn-secondary" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>
    </form>

    @error('to')
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $message }}</div>
    @enderror

    @include('admin.billing-reports._revenue')
    @include('admin.billing-reports._open')
    @include('admin.billing-reports._failed')
    @include('admin.billing-reports._credits')
@endsection
