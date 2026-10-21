{{-- One Menu Builder item: $item --}}
<div class="flex items-center gap-2 px-3 py-2 min-w-0">
    <i data-lucide="grip-vertical" class="drag-handle w-4 h-4 shrink-0 cursor-grab text-[var(--text-muted)]" title="{{ __('Drag to reorder') }}"></i>
    <i data-lucide="{{ $item->icon ?: 'circle' }}" class="w-4 h-4 shrink-0 text-[var(--text-secondary)]"></i>

    <div class="min-w-0">
        <span class="text-sm font-medium {{ $item->is_active ? 'text-[var(--text-primary)]' : 'line-through text-[var(--text-muted)]' }}">{{ $item->label }}</span>
        <span class="block text-xs truncate text-[var(--text-muted)]">{{ $item->route ?: __('no link') }}</span>
    </div>

    <div class="flex flex-wrap items-center gap-1 ms-auto shrink-0">
        @if($item->family_key)
            @if($item->min_edition === \App\Enums\Edition::Full)
                <span class="badge badge-active" title="{{ __('Shown in the Full edition only') }}">{{ __('Full') }}</span>
            @else
                <span class="badge badge-trial" title="{{ __('Shown in Lite and Full') }}">{{ __('Lite+') }}</span>
            @endif
        @endif
        @if($item->show_if)
            <span class="badge badge-info" title="{{ __('Show if') }}">{{ __('if') }} {{ $item->show_if }}</span>
        @endif
        @if($item->permission_key)
            <span class="badge badge-inactive" title="{{ __('Permission') }}">{{ $item->permission_key }}</span>
        @endif
        @unless($item->is_active)
            <span class="badge badge-warning">{{ __('Hidden') }}</span>
        @endunless

        <a href="{{ route('admin.menu-items.edit', $item->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
        </a>
        <button type="button" class="btn btn-xs btn-secondary" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-menu-item-{{ $item->id }}')">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
        </button>
    </div>

    <x-confirm-modal
        id="delete-menu-item-{{ $item->id }}"
        :title="__('Delete Menu Item?')"
        :message="__('Delete') . ' &quot;' . e($item->label) . '&quot;' . ($item->children()->exists() ? ' ' . __('and the items under it') : '') . '? ' . __('To hide it for now, edit it and switch it off instead.')"
        :action="route('admin.menu-items.destroy', $item->id)"
        method="DELETE"
        :confirm-text="__('Delete')"
        confirm-class="btn-danger"
        icon="trash-2"
    />
</div>
