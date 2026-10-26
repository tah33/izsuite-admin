{{-- Content > Footer Columns: each column's name and links, saved one at a time --}}
<div class="cms-panel {{ $slug !== $activeTab ? 'hidden' : '' }}" data-tab="{{ $slug }}">
    <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-semibold text-[var(--text-muted)]">{{ __($section['label']) }}</span>
        <button type="button" class="btn btn-outline btn-sm" id="add-column-btn">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('Add Column') }}
        </button>
    </div>

    <div id="footer-columns-container" class="space-y-4">
        @forelse($items[$slug] ?? [] as $index => $item)
            @php $colData = $item->data; @endphp
            <div class="footer-column-card p-4 bg-[var(--body-bg)] rounded-lg border border-[var(--card-border)] mb-3" data-id="{{ $item->id }}">
                <div class="item-header flex items-center justify-between mb-4 pb-3 border-b border-[var(--card-border)]">
                    <span class="item-number font-medium text-sm text-[var(--text-primary)] flex items-center gap-1.5">
                        <i data-lucide="grip-vertical" class="w-4 h-4 text-[var(--text-muted)] cursor-move"></i>
                        Column #{{ $index + 1 }}
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="save-column-btn btn btn-sm btn-outline flex items-center gap-1" title="Save">
                            <i data-lucide="save" class="w-3.5 h-3.5"></i> {{ __('Save') }}
                        </button>
                        <button type="button" class="delete-item-btn text-red-400 hover:text-red-600 transition p-1" data-id="{{ $item->id }}" title="Delete">
                            <i data-lucide="trash-2" class="w-4 h-4 pointer-events-none"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-xs">Column Name</label>
                    <input type="text" class="form-input col-name-input" value="{{ $colData['name'] ?? '' }}" placeholder="e.g. Product">
                </div>

                <label class="form-label text-xs">Links</label>
                <div class="col-links-list space-y-2">
                    @foreach($colData['links'] ?? [] as $li)
                        <div class="col-link-row flex items-center gap-2">
                            <input type="text" class="form-input flex-1 link-title" value="{{ $li['title'] ?? '' }}" placeholder="Link title">
                            <input type="text" class="form-input flex-1 link-url" value="{{ $li['url'] ?? '' }}" placeholder="URL e.g. /about">
                            <button type="button" class="remove-link-btn btn-icon-danger flex-shrink-0" title="Remove">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="add-link-btn btn btn-outline btn-sm mt-3" style="font-size: 0.7rem; padding: 0.3rem 0.6rem;">
                    <i data-lucide="plus" class="w-3 h-3"></i>
                    Add Link
                </button>
            </div>
        @empty
            <div class="empty-state text-center py-12 card text-[var(--text-muted)]">
                <i data-lucide="columns-3" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                <p>{{ __('No columns yet. Click "+ Add Column" to get started.') }}</p>
            </div>
        @endforelse
    </div>
</div>
