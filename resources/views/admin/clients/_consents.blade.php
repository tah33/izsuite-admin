{{-- The account's consent records, newest first (Doc 15 section 15.24 - Phase G3): written once, never changed. Expects $client and $consents. --}}
@php($Consent = \App\Models\Admin\ConsentRecord::class)
<div class="card p-6 mb-6">
    <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Consent Records') }}</h2>
    <p class="text-sm text-[var(--text-muted)] mb-4">
        {{ __('Product news by e-mail: :state.', ['state' => $client->marketing_consent ? __('given') : __('not given')]) }}
        {{ __('Each consent given or withdrawn is kept as it was, with where it came from.') }}
    </p>

    @if($consents->isEmpty())
        <p class="text-sm text-[var(--text-muted)]">{{ __('None recorded.') }}</p>
    @else
        <ul class="divide-y divide-[var(--card-border)]">
            @foreach($consents as $consent)
                <li class="py-2 flex flex-wrap justify-between gap-2 text-sm">
                    <span>
                        <span class="badge {{ $consent->given ? 'badge-success' : 'badge-inactive' }} me-1">{{ $consent->given ? __('Given') : __('Withdrawn') }}</span>
                        {{ __($Consent::KINDS[$consent->kind] ?? $consent->kind) }}
                        <span class="text-[var(--text-muted)]">· {{ __($Consent::SOURCES[$consent->source] ?? $consent->source) }}{{ $consent->ip_address ? ' · '.$consent->ip_address : '' }}</span>
                    </span>
                    <span class="text-xs text-[var(--text-muted)] whitespace-nowrap">{{ $consent->created_at->format('M d, Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
