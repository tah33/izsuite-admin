{{-- Where a business stands against its limits (Doc 15 section 15.18), as a badge. Expects $state, a UsageState. --}}
<span class="badge {{ [
    'ok'      => 'badge-success',
    'warning' => 'badge-warning',
    'full'    => 'badge-danger',
    'over'    => 'badge-danger',
][$state] ?? 'badge-inactive' }}">{{ __(\App\Support\UsageState::LABELS[$state] ?? $state) }}</span>
