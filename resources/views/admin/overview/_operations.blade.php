{{-- Storage the businesses use now, and the background jobs (Doc 15 section 15.4). --}}
<div class="card">
    <h3 class="section-title mb-4">{{ __('Storage and Background Jobs') }}</h3>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
        <div>
            <dt class="text-[var(--text-muted)]">{{ __('Storage used') }}</dt>
            <dd class="text-lg font-semibold text-[var(--text-primary)]">{{ \App\Support\Bytes::format($storage['bytes']) }}</dd>
        </div>
        <div>
            <dt class="text-[var(--text-muted)]">{{ __(':percent% of their plan\'s storage or more', ['percent' => $storage['warn_percent']]) }}</dt>
            <dd class="text-lg font-semibold {{ $storage['near_limit'] > 0 ? 'text-[var(--warning)]' : 'text-[var(--text-primary)]' }}">{{ trans_choice(':count business|:count businesses', $storage['near_limit']) }}</dd>
        </div>
        <div>
            <dt class="text-[var(--text-muted)]">{{ __('Jobs failed in these days') }}</dt>
            <dd class="text-lg font-semibold {{ $jobs['failed'] > 0 ? 'text-[var(--danger)]' : 'text-[var(--text-primary)]' }}">
                {{ number_format($jobs['failed']) }}
                <span class="text-xs font-normal text-[var(--text-muted)]">{{ __(':count kept in all', ['count' => number_format($jobs['kept'])]) }}</span>
            </dd>
        </div>
        <div>
            <dt class="text-[var(--text-muted)]">{{ __('Jobs waiting now') }}</dt>
            <dd class="text-lg font-semibold text-[var(--text-primary)]">{{ $jobs['waiting'] === null ? __('Queue not reached') : number_format($jobs['waiting']) }}</dd>
        </div>
    </dl>
</div>
