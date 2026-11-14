{{-- A business's usage against its plan, what it holds, and the apps it has on (Doc 15 section 15.5). --}}
<div class="card p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Usage and Apps') }}</h2>
        @if($may('admin.usage.show'))
            <a href="{{ route('admin.usage.show', $business->id) }}" class="text-sm font-medium text-[var(--primary)]">{{ __('Usage History') }}</a>
        @endif
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-5">
        @foreach(\App\Support\BusinessUsage::of($business) as $line)
            <div class="p-3 rounded-lg bg-[var(--content-bg)]">
                <p class="text-sm font-semibold {{ $line['reached'] ? 'text-[var(--danger)]' : 'text-[var(--text-primary)]' }}">{{ $line['label'] }}</p>
                @if($line['reached'])
                    <p class="text-xs text-[var(--danger)]">{{ __('At or past the limit') }}</p>
                @endif
            </div>
        @endforeach
        <div class="p-3 rounded-lg bg-[var(--content-bg)]">
            <p class="text-sm font-semibold text-[var(--text-primary)]">{{ trans_choice('{1} :count contact|[0,*] :count contacts', $business->contacts_count, ['count' => $business->contacts_count]) }}</p>
        </div>
        <div class="p-3 rounded-lg bg-[var(--content-bg)]">
            <p class="text-sm font-semibold text-[var(--text-primary)]">{{ trans_choice('{1} :count item|[0,*] :count items', $business->items_count, ['count' => $business->items_count]) }}</p>
        </div>
    </div>

    <p class="text-sm text-[var(--text-muted)] mb-2">{{ __('Apps on') }}</p>
    <div class="flex flex-wrap gap-2">
        @forelse($business->workspaceApps->sortBy(fn ($row) => $row->app?->sort_order) as $row)
            <span class="badge badge-info">{{ $row->app?->name }}</span>
        @empty
            <span class="text-sm text-[var(--text-muted)]">{{ __('None') }}</span>
        @endforelse
    </div>
</div>
