@extends('layouts.admin')
@section('title', __('Country'))

{{-- One country (Doc 15 section 15.19): its name and calling code, and the defaults a new business there takes, by version from a day. Expects $country, $versions, $businesses, $payments, $currencies, $zones and $example. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.countries.index') }}" class="text-[var(--primary)]">{{ __('Countries') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $country->name }}</span>
        </div>
        <h1 class="page-title">{{ $country->name }}</h1>
        <p class="page-subtitle"><code>{{ $country->code }}</code> · {{ trans_choice(':count business is based here|:count businesses are based here', $businesses) }}</p>
        <p class="text-xs mt-1 text-[var(--text-muted)]">
            {{ $payments->isEmpty() ? __('None of izSuite\'s ways to pay is offered to a business billed here.') : __('Ways to pay offered to a business billed here: :methods.', ['methods' => $payments->implode(', ')]) }}
            <a href="{{ route('admin.payment-methods.index') }}" class="text-[var(--primary)]">{{ __('Payment Methods') }}</a>
        </p>
    </div>

    <form action="{{ route('admin.countries.update', $country->id) }}" method="POST" class="card">
        @csrf
        @method('PUT')

        <h2 class="section-title mb-4">{{ __('Country') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="form-label" for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" class="form-input" maxlength="100" required value="{{ old('name', $country->name) }}">
            </div>
            <div>
                <label class="form-label" for="calling_code">{{ __('Calling Code') }}</label>
                <input type="text" id="calling_code" name="calling_code" class="form-input" maxlength="4" inputmode="numeric" value="{{ old('calling_code', $country->calling_code) }}" placeholder="880">
            </div>
        </div>
        <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Saved at once, everywhere the country is shown. Documents already issued keep the name they printed.') }}</p>

        @include('admin.countries._defaults')
        @include('admin.countries._version-date')

        @if($may('admin.countries.edit'))
            <div class="mt-6">
                <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>{{ __('Save') }}</button>
            </div>
        @endif
    </form>

    @include('admin.countries._versions')
@endsection
