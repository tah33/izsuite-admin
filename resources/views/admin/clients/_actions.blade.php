{{-- Support's actions on the account (Doc 15 section 15.7), each as the admin's role allows. None shows or sets a password. --}}
@php($locked = $client->status === \App\Models\User\User::LOCKED)
<div class="flex flex-wrap items-center gap-2 mb-6">
    @if(! $client->email_verified_at && $may('admin.clients.verification'))
        <form action="{{ route('admin.clients.verification', $client->id) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="mail-check" class="w-4 h-4"></i>{{ __('Resend Verification') }}
            </button>
        </form>
    @endif
    @if($may('admin.clients.recovery'))
        <button type="button" class="btn btn-secondary" data-modal-open="client-recovery-modal">
            <i data-lucide="key-round" class="w-4 h-4"></i>{{ __('Send Reset Link') }}
        </button>
    @endif
    @if($may('admin.clients.sign-out'))
        <button type="button" class="btn btn-secondary" data-modal-open="client-sign-out-modal">
            <i data-lucide="log-out" class="w-4 h-4"></i>{{ __('Sign Out Everywhere') }}
        </button>
    @endif
    @if($client->two_factor_confirmed_at && $may('admin.clients.two-factor'))
        <button type="button" class="btn btn-secondary" data-modal-open="client-two-factor-modal">
            <i data-lucide="shield-off" class="w-4 h-4"></i>{{ __('Reset Two-Factor') }}
        </button>
    @endif
    @if($locked && $may('admin.clients.unlock'))
        <button type="button" class="btn btn-primary" data-modal-open="client-unlock-modal">
            <i data-lucide="lock-open" class="w-4 h-4"></i>{{ __('Unlock') }}
        </button>
    @elseif(! $locked && $client->status !== \App\Models\User\User::CLOSED && $may('admin.clients.lock'))
        <button type="button" class="btn btn-danger" data-modal-open="client-lock-modal">
            <i data-lucide="lock" class="w-4 h-4"></i>{{ __('Lock') }}
        </button>
    @endif
</div>
