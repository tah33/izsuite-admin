{{-- One service as found (Doc 15 section 15.23): its state, what was found, and the facts beside it - with Open an incident when it alerts. Expects $status, a ServiceStatus, and $may. --}}
@php
    $badge = [
        'ok'      => 'badge-success',
        'warning' => 'badge-warning',
        'failing' => 'badge-danger',
        'unused'  => 'badge-inactive',
    ][$status->state] ?? 'badge-inactive';
@endphp
<div class="card {{ $status->state === 'failing' ? 'border-[var(--danger)]' : '' }}">
    <div class="flex items-start justify-between gap-3 mb-2">
        <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ __($status->name) }}</h3>
        <span class="badge {{ $badge }}">{{ __(\App\Support\ServiceStatus::LABELS[$status->state]) }}</span>
    </div>
    <p class="text-sm {{ $status->alerting() ? 'text-[var(--text-primary)]' : 'text-[var(--text-secondary)]' }}">{{ __($status->summary) }}</p>
    @if($status->alerting() && $may('admin.incidents.create'))
        <a href="{{ route('admin.incidents.create', ['service' => $status->key]) }}" class="inline-block mt-1 text-xs font-medium text-[var(--primary)]">{{ __('Open an incident') }}</a>
    @endif
    @if($status->facts !== [])
        <dl class="mt-3 grid grid-cols-1 gap-1 text-xs">
            @foreach($status->facts as $label => $value)
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--text-muted)]">{{ __($label) }}</dt>
                    <dd class="text-end text-[var(--text-primary)]">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
