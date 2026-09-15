@extends('layouts.admin')

@section('title', __('My Profile'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Profile Information') }}</h2>
        <p class="text-sm text-[var(--text-muted)] mb-6">{{ __('Update your account name and email address.') }}</p>

        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="form-group flex flex-col gap-1.5">
                    <label for="first_name" class="form-label text-sm text-[var(--text-muted)]">{{ __('First Name') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="form-input @error('first_name') border-[var(--danger)] @enderror">
                    @error('first_name')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                </div>

                <div class="form-group flex flex-col gap-1.5">
                    <label for="last_name" class="form-label text-sm text-[var(--text-muted)]">{{ __('Last Name') }}</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="form-input @error('last_name') border-[var(--danger)] @enderror">
                    @error('last_name')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                </div>

                <div class="form-group flex flex-col gap-1.5">
                    <label for="email" class="form-label text-sm text-[var(--text-muted)]">{{ __('Email Address') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-input @error('email') border-[var(--danger)] @enderror">
                    @error('email')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-[var(--card-border)] flex justify-end">
                <button type="submit" class="btn btn-primary flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    {{ __('Save Changes') }}
                </button>
            </div>
        </form>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-4">{{ __('Account Details') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[var(--text-muted)] mb-0.5">{{ __('Role') }}</p>
                <p class="font-medium text-[var(--text-primary)]">{{ $user->role?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-[var(--text-muted)] mb-0.5">{{ __('Member Since') }}</p>
                <p class="font-medium text-[var(--text-primary)]">{{ $user->created_at->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-[var(--text-muted)] mb-0.5">{{ __('Last Login') }}</p>
                <p class="font-medium text-[var(--text-primary)]">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : '-' }}</p>
            </div>
            <div>
                <p class="text-[var(--text-muted)] mb-0.5">{{ __('Status') }}</p>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $user->status === 'active' ? 'bg-[var(--success-light)] text-[var(--success)]' : 'bg-[var(--danger-light)] text-[var(--danger)]' }}">
                    {{ ucfirst($user->status ?? 'active') }}
                </span>
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Change Password') }}</h2>
        <p class="text-sm text-[var(--text-muted)] mb-6">{{ __('Ensure your account uses a long, random password to stay secure.') }}</p>

        <form method="POST" action="{{ route('admin.profile.password') }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div class="form-group flex flex-col gap-1.5">
                    <label for="current_password" class="form-label text-sm text-[var(--text-muted)]">{{ __('Current Password') }}</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="form-input @error('current_password') border-[var(--danger)] @enderror">
                    @error('current_password')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group flex flex-col gap-1.5">
                        <label for="password" class="form-label text-sm text-[var(--text-muted)]">{{ __('New Password') }}</label>
                        <input type="password" id="password" name="password" autocomplete="new-password" class="form-input @error('password') border-[var(--danger)] @enderror">
                        @error('password')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                        <p class="text-xs text-[var(--text-muted)]">{{ __('At least 8 characters, with upper and lower case, a number and a symbol.') }}</p>
                    </div>

                    <div class="form-group flex flex-col gap-1.5">
                        <label for="password_confirmation" class="form-label text-sm text-[var(--text-muted)]">{{ __('Confirm Password') }}</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-input">
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-4 border-t border-[var(--card-border)] flex justify-end">
                <button type="submit" class="btn btn-primary flex items-center gap-2">
                    <i data-lucide="lock" class="w-4 h-4"></i>
                    {{ __('Update Password') }}
                </button>
            </div>
        </form>
    </div>

    <div class="card p-6 border border-[var(--danger-light)]">
        <h2 class="text-lg font-semibold text-[var(--danger)] mb-1">{{ __('Delete Account') }}</h2>
        <p class="text-sm text-[var(--text-muted)] mb-6">{{ __('This action permanently removes your account and signs you out.') }}</p>

        @if($user->isSuperAdmin())
            <p class="text-sm text-[var(--text-muted)]">{{ __('The super admin account cannot be deleted from the panel.') }}</p>
        @else
            <form method="POST" action="{{ route('admin.profile.destroy') }}" class="space-y-4">
                @csrf
                @method('DELETE')

                <div class="form-group flex flex-col gap-1.5">
                    <label for="delete_password" class="form-label text-sm text-[var(--text-muted)]">{{ __('Confirm Password') }}</label>
                    <input type="password" id="delete_password" name="password" autocomplete="current-password" class="form-input @error('password') border-[var(--danger)] @enderror">
                    @error('password')<p class="text-xs text-[var(--danger)]">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-danger">{{ __('Delete My Account') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
