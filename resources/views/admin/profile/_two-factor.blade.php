{{-- The admin's own two-factor sign-in (Doc 15 section 15.2): on for every admin, with new recovery codes on demand. --}}
<div class="card p-6">
    <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Two-Factor Sign-In') }}</h2>

    @if($twoFactorOn)
        <p class="text-sm text-[var(--text-muted)] mb-5">
            {{ __('On since :date. You sign in with a code from your authenticator app as well as your password.', ['date' => $user->two_factor_confirmed_at->format('M d, Y')]) }}
            {{ trans_choice('{0} You have no recovery codes left: make new ones.|{1} You have 1 recovery code left.|[2,*] You have :count recovery codes left.', $recoveryCodesLeft) }}
        </p>

        <form method="POST" action="{{ route('admin.two-factor.new-recovery-codes') }}" class="flex flex-col md:flex-row md:items-end gap-4">
            @csrf
            <div class="form-group flex flex-col gap-1.5 flex-1">
                <label for="recovery_password" class="form-label text-sm text-[var(--text-muted)]">{{ __('Password') }}</label>
                <input type="password" id="recovery_password" name="password" autocomplete="current-password" class="form-input">
            </div>
            <button type="submit" class="btn btn-secondary flex items-center gap-2">
                <i data-lucide="key-round" class="w-4 h-4"></i>
                {{ __('Make New Recovery Codes') }}
            </button>
        </form>
        <p class="text-xs text-[var(--text-muted)] mt-2">{{ __('New codes replace all the old ones. Lost your phone? A Super Admin can reset your two-factor sign-in.') }}</p>
    @else
        <p class="text-sm text-[var(--text-muted)] mb-4">{{ __('Not set up yet. Every admin needs it: you are asked to set it up at your next sign-in.') }}</p>
        <a href="{{ route('admin.two-factor.setup') }}" class="btn btn-primary">{{ __('Set It Up Now') }}</a>
    @endif
</div>
