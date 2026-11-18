{{-- The platform's alerts (Doc 15 sections 15.4 and 15.23): each service warning or failing, as Service Health finds it now, and the incidents not resolved yet. Expects $alerts and $incidents (null: not for this admin). --}}
<div class="card mb-6 {{ $alerts !== [] || ($incidents && $incidents->isNotEmpty()) ? 'border-[var(--warning)]' : '' }}">
    <div class="flex items-center justify-between mb-3">
        <h3 class="section-title">{{ __('Platform Alerts') }}</h3>
        <a href="{{ route('admin.service-health.index') }}" class="text-sm font-medium text-[var(--primary)]">{{ __('Open Service Health') }}</a>
    </div>

    @forelse($alerts as $alert)
        <div class="flex items-start gap-3 py-2 border-t border-[var(--card-border)] first:border-t-0">
            <span class="badge {{ $alert->state === 'failing' ? 'badge-danger' : 'badge-warning' }}">{{ __(\App\Support\ServiceStatus::LABELS[$alert->state]) }}</span>
            <p class="text-sm"><strong class="text-[var(--text-primary)]">{{ __($alert->name) }}:</strong> {{ __($alert->summary) }}</p>
        </div>
    @empty
        <p class="text-sm text-[var(--text-muted)]">{{ __('None: every service is OK or not used.') }}</p>
    @endforelse

    @if($incidents && $incidents->isNotEmpty())
        <p class="text-sm font-semibold mt-4 mb-1 text-[var(--text-primary)]">{{ trans_choice(':count incident not resolved|:count incidents not resolved', $incidents->count()) }}</p>
        @foreach($incidents as $incident)
            <div class="flex flex-wrap items-center gap-2 py-1">
                <a href="{{ route('admin.incidents.show', $incident->id) }}" class="text-sm text-[var(--primary)]">{{ $incident->title }}</a>
                @include('admin.incidents._badges')
            </div>
        @endforeach
    @endif
</div>
