{{--
    A critical action's confirmation (Doc 15 section 15.25): what the change
    affects, then the admin's password again and the reason, which the audit
    log keeps. CriticalActionService checks them. Expects $impact; $id makes
    the fields' ids unique when a page has more than one.
--}}
@php($id = $id ?? 'critical')
<div class="rounded-lg border border-[var(--warning)] bg-[var(--warning-light)] p-4 text-start {{ $class ?? '' }}">
    <p class="text-sm font-semibold text-[var(--text-primary)] mb-1 flex items-center gap-2">
        <i data-lucide="shield-alert" class="w-4 h-4"></i>{{ __('A critical change') }}
    </p>
    <p class="text-sm text-[var(--text-secondary)] mb-3">{{ $impact }} {{ __('Confirm it with your password and the reason, which the audit log keeps.') }}</p>

    <div class="grid grid-cols-1 {{ ($compact ?? false) ? '' : 'md:grid-cols-2' }} gap-3">
        <div class="form-group flex flex-col gap-1.5">
            <label for="{{ $id }}_confirm_password" class="form-label text-sm">{{ __('Your password') }}</label>
            <input type="password" id="{{ $id }}_confirm_password" name="confirm_password" autocomplete="current-password" class="form-input">
        </div>
        <div class="form-group flex flex-col gap-1.5">
            <label for="{{ $id }}_reason" class="form-label text-sm">{{ __('Reason') }}</label>
            <input type="text" id="{{ $id }}_reason" name="reason" maxlength="500" class="form-input" placeholder="{{ __('Why this change is being made') }}">
        </div>
    </div>
</div>
