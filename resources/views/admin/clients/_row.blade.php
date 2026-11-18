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
</tr>
