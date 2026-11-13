{{-- One account in Admin > Users (Doc 15 section 15.7). Expects $client, with ClientRepository::paginate()'s counts. --}}
@php($alerts = \App\Support\AccountAlerts::of($client))
<tr>
    <td>
        <div class="flex items-center gap-3">
            <div class="user-avatar w-9 h-9 shrink-0">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</div>
            <div class="min-w-0">
                <a href="{{ route('admin.clients.show', $client->id) }}" class="font-semibold text-[var(--text-primary)] hover:text-[var(--primary)]">{{ $client->name }}</a>
                <span class="block text-xs text-[var(--text-muted)]">{{ $client->email }}</span>
                @if($client->phone)
                    <span class="block text-xs text-[var(--text-muted)]">{{ $client->phone }}</span>
                @endif
            </div>
        </div>
    </td>
    <td>
        @foreach($client->workspaces as $workspace)
            <span class="block text-sm">{{ $workspace->name }} <span class="text-xs text-[var(--text-muted)]">· {{ $workspace->package?->name }}</span></span>
        @endforeach
        <span class="block text-xs text-[var(--text-muted)] whitespace-nowrap">{{ trans_choice('In :count business|In :count businesses', $client->businesses_count, ['count' => $client->businesses_count]) }}</span>
    </td>
    <td class="text-xs whitespace-nowrap">
        @include('admin.clients._status-badge', ['status' => $client->status])
        <span class="block mt-1 {{ $client->email_verified_at ? 'text-[var(--text-muted)]' : 'text-[var(--warning)] font-medium' }}">{{ $client->email_verified_at ? __('Email verified') : __('Email not verified') }}</span>
        <span class="block text-[var(--text-muted)]">{{ $client->two_factor_confirmed_at ? __('Two-factor on') : __('Two-factor off') }}</span>
    </td>
    <td class="text-xs whitespace-nowrap">
        @forelse($alerts as $alert)
            <span class="block text-[var(--danger)] font-medium">{{ $alert }}</span>
        @empty
            <span class="text-[var(--text-muted)]">{{ __('None') }}</span>
        @endforelse
    </td>
    <td class="text-xs text-[var(--text-muted)] whitespace-nowrap">
        <span class="block text-sm">{{ $client->created_at?->format('M d, Y') }}</span>
        {{ $client->last_login_at ? __('Signed in :when', ['when' => $client->last_login_at->diffForHumans(short: true)]) : __('Never signed in') }}
    </td>
    <td class="text-end">
        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
            {{-- Opens the website, signed in as the client, in a new tab. data-no-ajax: the
                 panel's AJAX forms would swallow the redirect to the website. --}}
            @if($client->status === 'active' && $client->email_verified_at)
                <form action="{{ route('admin.clients.login-as', $client->id) }}" method="POST" target="_blank" rel="noopener" data-no-ajax>
                    @csrf
                    <button type="submit" class="btn btn-xs btn-primary">
                        <i data-lucide="log-in" class="w-3.5 h-3.5"></i> {{ __('Login as') }}
                    </button>
                </form>
            @else
                <span title="{{ $client->status === 'locked' ? __('This user\'s account is locked.') : ($client->status !== 'active' ? __('This user\'s account is not active.') : __('This user has not verified their email yet.')) }}">
                    <button type="button" class="btn btn-xs btn-secondary opacity-50 pointer-events-none" disabled>
                        <i data-lucide="log-in" class="w-3.5 h-3.5"></i> {{ __('Login as') }}
                    </button>
                </span>
            @endif
        </div>
    </td>
</tr>
