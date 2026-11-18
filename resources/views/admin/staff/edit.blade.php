@extends('layouts.admin')
@section('title', __('Edit Staff') . ' — ' . $staffUser->name)

@section('content')
    {{-- Breadcrumb --}}
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.staff.index') }}" class="text-[var(--primary)]">{{ __('Staff') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $staffUser->name }}</span>
        </div>
        <h1 class="page-title">{{ __('Edit Staff Member') }}</h1>
        <p class="page-subtitle">{{ __('Update staff details and role') }}</p>
    </div>

    <form action="{{ route('admin.staff.update', $staffUser->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card mb-6">
            @include('admin.staff._form')
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> {{ __('Save Changes') }}
            </button>
            <a href="{{ route('admin.staff.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>

    {{-- Doc 15 section 15.2: their two-factor sign-in, and their sign-in history. --}}
    <div class="card p-6 mt-6">
        <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Two-Factor Sign-In') }}</h2>
        <p class="text-sm text-[var(--text-muted)] mb-4">
            {{ $twoFactorOn
                ? __('On since :date.', ['date' => $staffUser->two_factor_confirmed_at->format('M d, Y')])
                : __('Not set up yet: they set it up at their next sign-in.') }}
        </p>

        @if($twoFactorOn && auth()->user()->isSuperAdmin())
            <button type="button" class="btn btn-secondary" onclick="openConfirmModal('reset-two-factor')">
                <i data-lucide="shield-off" class="w-4 h-4"></i> {{ __('Reset Two-Factor Sign-In') }}
            </button>
            <p class="text-xs text-[var(--text-muted)] mt-2">{{ __('For a lost phone. Check it is them asking first.') }}</p>

            <x-confirm-modal
                id="reset-two-factor"
                :title="__('Reset Two-Factor Sign-In?')"
                :message="__(':name is signed out, and sets two-factor sign-in up again at their next sign-in.', ['name' => $staffUser->name])"
                :action="route('admin.staff.two-factor.reset', $staffUser->id)"
                method="POST"
                :confirm-text="__('Reset')"
                confirm-class="btn-danger"
                icon="shield-off"
            >
                @include('admin.partials.critical-confirm', ['impact' => __('Until they set it up again, anyone with their password could sign in and set it up.'), 'id' => 'reset', 'compact' => true])
            </x-confirm-modal>
        @endif
    </div>

    <div class="mt-6">
        @include('admin.partials.sign-ins', ['signIns' => $signIns, 'subtitle' => __('Their latest sign-ins to the admin panel, and the attempts that failed.')])
    </div>
@endsection
