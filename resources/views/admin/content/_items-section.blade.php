{{-- Content: one landing-page section kept as items (Stats, Features...) --}}
<div class="cms-panel {{ $slug !== $activeTab ? 'hidden' : '' }}" data-tab="{{ $slug }}">
    <div class="flex items-center justify-between mb-4">
        <div>
            <span class="text-sm font-semibold text-[var(--text-muted)]">
                {{ __($section['label']) }}
            </span>
        </div>
        <button type="button" class="btn btn-outline btn-sm add-item-btn" data-section="{{ $slug }}">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('Add :item', ['item' => __($section['item_label'])]) }}
        </button>
    </div>

    <div class="items-container space-y-3" data-section="{{ $slug }}">
        @forelse($items[$slug] ?? [] as $index => $item)
            @include('admin.content._item-card', [
                'section' => $slug,
                'sectionDef' => $section,
                'item' => $item,
                'index' => $index,
            ])
        @empty
            <div class="empty-state text-center py-12 card text-[var(--text-muted)]">
                <i data-lucide="{{ $section['icon'] }}" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                <p>{{ __('No :items yet. Click "+ Add :item" to get started.', ['items' => strtolower($section['label']), 'item' => $section['item_label']]) }}</p>
            </div>
        @endforelse
    </div>
</div>
