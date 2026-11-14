{{-- The apps most businesses have active now (Doc 15 section 15.4), with a bar for each against the first. --}}
<div class="card">
    <h3 class="section-title mb-4">{{ __('Most Activated Apps') }}</h3>

    @php($most = max(1, (int) ($apps->first()->businesses ?? 1)))
    @forelse($apps as $app)
        <div class="mb-3 last:mb-0">
            <div class="flex items-center justify-between text-sm mb-1">
                <span class="text-[var(--text-primary)]">{{ $app->name }}</span>
                <span class="text-[var(--text-muted)]">{{ trans_choice(':count business|:count businesses', (int) $app->businesses) }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-[var(--bg-secondary)] overflow-hidden">
                <div class="h-full rounded-full bg-[var(--primary)]" style="width: {{ round((int) $app->businesses / $most * 100) }}%"></div>
            </div>
        </div>
    @empty
        <p class="text-sm text-[var(--text-muted)]">{{ __('No business has an app active.') }}</p>
    @endforelse
</div>
