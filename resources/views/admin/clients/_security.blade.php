{{-- The account's security alerts (Doc 15 section 15.7): its recent failed sign-ins, and other accounts that give its phone number. --}}
@php($alerts = \App\Support\AccountAlerts::of($client))
<div class="card p-6 mb-6">
    <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Security Alerts') }}</h2>
    <p class="text-sm text-[var(--text-muted)] mb-4">{{ __(':count or more failed sign-ins in :days days, or a phone number another account gives too.', ['count' => \App\Support\AccountAlerts::FAILED_SIGN_INS, 'days' => \App\Support\AccountAlerts::DAYS]) }}</p>

    @forelse($alerts as $alert)
        <p class="text-sm font-medium text-[var(--danger)] flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4"></i>{{ $alert }}</p>
    @empty
        <p class="text-sm text-[var(--text-muted)]">{{ __('None.') }}</p>
    @endforelse

    @if($failedSignIns->isNotEmpty())
        <h3 class="text-sm font-semibold text-[var(--text-primary)] mt-5 mb-1">{{ __('Latest failed sign-ins') }}</h3>
        <ul class="divide-y divide-[var(--card-border)]">
            @foreach($failedSignIns as $failure)
                <li class="py-2 flex flex-wrap justify-between gap-2 text-sm">
                    <span>{{ $failure->description }} <span class="text-[var(--text-muted)]">· {{ $failure->ip_address ?? __('Unknown IP address') }}</span></span>
                    <span class="text-xs text-[var(--text-muted)] whitespace-nowrap">{{ $failure->created_at->format('M d, Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if($sharingPhone->isNotEmpty())
        <h3 class="text-sm font-semibold text-[var(--text-primary)] mt-5 mb-1">{{ __('Accounts with the same phone number') }}</h3>
        <ul class="divide-y divide-[var(--card-border)]">
            @foreach($sharingPhone as $other)
                <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span>
                        <a href="{{ route('admin.clients.show', $other->id) }}" class="font-medium text-[var(--primary)]">{{ $other->name }}</a>
                        <span class="text-[var(--text-muted)]">· {{ $other->email }} · {{ __('Joined :date', ['date' => $other->created_at?->format('M d, Y')]) }}</span>
                    </span>
                    @include('admin.clients._status-badge', ['status' => $other->status])
                </li>
            @endforeach
        </ul>
    @endif
</div>
