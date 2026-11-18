@extends('layouts.admin')
@section('title', __('Place a Legal Hold'))

{{-- A legal hold on a business (Doc 15 section 15.24): its records are kept from deletion until it is released. --}}
@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.legal-holds.index') }}" class="text-[var(--primary)]">{{ __('Legal Holds') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __('Place a Hold') }}</span>
        </div>
        <h1 class="page-title">{{ __('Place a Legal Hold') }}</h1>
        <p class="page-subtitle">{{ __('From now, the business\'s owner cannot delete it and nobody in it can delete its records. Its people are told only that izSuite is keeping them.') }}</p>
    </div>

    <form action="{{ route('admin.legal-holds.store') }}" method="POST" class="card max-w-2xl">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="business">{{ __('The Business') }}</label>
                <input type="text" id="business" name="business" class="form-input" maxlength="20" required value="{{ old('business') }}" placeholder="BW-000012">
            </div>
            <div>
                <label class="form-label" for="reference">{{ __('Reference') }}</label>
                <input type="text" id="reference" name="reference" class="form-input" maxlength="100" value="{{ old('reference') }}" placeholder="{{ __('e.g. a case number') }}">
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="reason">{{ __('Why') }}</label>
                <textarea id="reason" name="reason" rows="4" class="form-input" maxlength="2000" required>{{ old('reason') }}</textarea>
                <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('For izSuite only: the business does not see it.') }}</p>
            </div>
        </div>
        <div class="mt-6">
            <button type="submit" class="btn btn-primary"><i data-lucide="lock-keyhole" class="w-4 h-4"></i>{{ __('Place the Hold') }}</button>
        </div>
    </form>
@endsection
