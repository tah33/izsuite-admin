@extends('layouts.admin')
@section('title', __('Log a Privacy Request'))

{{-- A privacy request as it came (Doc 15 section 15.24): who asked, how, and what it is about. Its asker is checked next. --}}
@php
    $Privacy = \App\Models\Admin\PrivacyRequest::class;
    $hint    = 'text-xs mt-1 text-[var(--text-muted)]';
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.privacy-requests.index') }}" class="text-[var(--primary)]">{{ __('Privacy Requests') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __('Log a Request') }}</span>
        </div>
        <h1 class="page-title">{{ __('Log a Privacy Request') }}</h1>
        <p class="page-subtitle">{{ __('As it came. Nothing is done until you have checked that the person asking is who they say.') }}</p>
    </div>

    <form action="{{ route('admin.privacy-requests.store') }}" method="POST" class="card max-w-3xl">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="type">{{ __('Request') }}</label>
                <select id="type" name="type" class="form-select" required>
                    @foreach($Privacy::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="received_on">{{ __('Received On') }}</label>
                <input type="date" id="received_on" name="received_on" class="form-input" max="{{ today()->toDateString() }}" value="{{ old('received_on', today()->toDateString()) }}" required>
                <p class="{{ $hint }}">{{ __('Due :days days after.', ['days' => $Privacy::DUE_DAYS]) }}</p>
            </div>
            <div>
                <label class="form-label" for="requester_name">{{ __('Asked By') }}</label>
                <input type="text" id="requester_name" name="requester_name" class="form-input" maxlength="150" required value="{{ old('requester_name') }}">
            </div>
            <div>
                <label class="form-label" for="requester_email">{{ __('Their E-mail') }}</label>
                <input type="email" id="requester_email" name="requester_email" class="form-input" maxlength="190" required value="{{ old('requester_email') }}">
                <p class="{{ $hint }}">{{ __('Where the answer, and an export\'s link, go.') }}</p>
            </div>
            <div>
                <label class="form-label" for="received_via">{{ __('Came By') }}</label>
                <select id="received_via" name="received_via" class="form-select" required>
                    @foreach($Privacy::CHANNELS as $value => $label)
                        <option value="{{ $value }}" @selected(old('received_via') === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div></div>
            <div>
                <label class="form-label" for="account_email">{{ __('The Account') }}</label>
                <input type="email" id="account_email" name="account_email" class="form-input" maxlength="190" value="{{ old('account_email') }}" placeholder="owner@example.com">
                <p class="{{ $hint }}">{{ __('By its e-mail. Needed to close an account.') }}</p>
            </div>
            <div>
                <label class="form-label" for="business">{{ __('The Business') }}</label>
                <input type="text" id="business" name="business" class="form-input" maxlength="20" value="{{ old('business') }}" placeholder="BW-000012">
                <p class="{{ $hint }}">{{ __('By its ID. Needed for a data export; the account above must be its owner or a member.') }}</p>
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="details">{{ __('What They Asked') }}</label>
                <textarea id="details" name="details" rows="5" class="form-input" maxlength="5000" required>{{ old('details') }}</textarea>
            </div>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>{{ __('Log the Request') }}</button>
        </div>
    </form>
@endsection
