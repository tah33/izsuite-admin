{{-- The incidents not resolved yet (Doc 15 section 15.23), on Service Health. Expects $incidents and $may. --}}
<div class="card mb-6 {{ $incidents->isNotEmpty() ? 'border-[var(--warning)]' : '' }}">
    <div class="flex flex-wrap items-center gap-3 mb-3">
        <h3 class="section-title flex-1">{{ __('Incidents Not Resolved') }}</h3>
        <a href="{{ route('admin.incidents.index') }}" class="text-sm font-medium text-[var(--primary)]">{{ __('All Incidents') }}</a>
        @if($may('admin.incidents.create'))
            <a href="{{ route('admin.incidents.create') }}" class="btn btn-sm btn-secondary"><i data-lucide="plus" class="w-4 h-4"></i>{{ __('Open Incident') }}</a>
        @endif
    </div>

    @forelse($incidents as $incident)
        <div class="flex flex-wrap items-center gap-2 py-2 border-t border-[var(--card-border)] first:border-t-0">
            <a href="{{ route('admin.incidents.show', $incident->id) }}" class="font-semibold text-sm text-[var(--primary)]">{{ $incident->title }}</a>
            @include('admin.incidents._badges')
            <span class="text-xs text-[var(--text-muted)] ms-auto">
                {{ $incident->service ? __(\App\Support\ServiceStatus::NAMES[$incident->service] ?? $incident->service).' · ' : '' }}{{ $incident->responsible?->name ?? __('Nobody yet') }} · {{ __('since :time', ['time' => $incident->started_at->diffForHumans()]) }}
            </span>
        </div>
    @empty
        <p class="text-sm text-[var(--text-muted)]">{{ __('None.') }}</p>
    @endforelse
</div>
