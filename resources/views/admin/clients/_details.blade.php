{{-- A user's account: how to reach them, its state, and its defaults. --}}
<div class="card mb-6">
    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Email') }}</dt>
            <dd class="font-medium"><a href="mailto:{{ $client->email }}" class="text-[var(--primary)] hover:underline">{{ $client->email }}</a></dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Phone') }}</dt>
            <dd class="font-medium">{{ $client->phone ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Username') }}</dt>
            <dd class="font-medium">{{ $client->username ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Status') }}</dt>
            <dd>
                @if($client->status === 'active')
                    <span class="badge badge-success">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-inactive">{{ __('Inactive') }}</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Email verified') }}</dt>
            <dd class="font-medium">{{ $client->email_verified_at?->format('M d, Y') ?? __('Not yet') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Last Login') }}</dt>
            <dd class="font-medium">{{ $client->last_login_at?->format('M d, Y H:i') ?? __('Never') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Joined') }}</dt>
            <dd class="font-medium">{{ $client->created_at?->format('M d, Y') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Timezone') }}</dt>
            <dd class="font-medium">{{ $client->timezone ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-[var(--text-muted)]">{{ __('Currency') }}</dt>
            <dd class="font-medium">{{ $client->currency ?: '—' }}</dd>
        </div>
    </dl>
</div>
