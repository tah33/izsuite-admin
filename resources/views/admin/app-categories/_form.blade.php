{{-- Shared App Category form partial — used by create.blade.php and edit.blade.php --}}
@php
    $isEdit = isset($appCategory) && $appCategory->exists;
@endphp

{{-- Name --}}
<div class="mb-4">
    <label class="form-label" for="name">{{ __('Category Name') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $isEdit ? $appCategory->name : '') }}" class="form-input" placeholder="Productivity, Finance, Communication..." required>
    @error('name')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    {{-- Icon --}}
    <div>
        <label class="form-label" for="icon">{{ __('Icon') }}</label>
        <input type="text" id="icon" name="icon" value="{{ old('icon', $isEdit ? $appCategory->icon : '') }}" class="form-input" placeholder="shopping-bag">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('A lucide icon name, shown in the Apps Center.') }}</p>
        @error('icon')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    {{-- Sort order --}}
    <div>
        <label class="form-label" for="sort_order">{{ __('Order') }}</label>
        <input type="number" id="sort_order" name="sort_order" min="0" step="1" value="{{ old('sort_order', $isEdit ? $appCategory->sort_order : 0) }}" class="form-input">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Lower comes first in the Apps Center.') }}</p>
        @error('sort_order')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="divider"></div>

{{-- Is Active (toggle switch) --}}
<div class="mb-4">
    <div class="flex items-center gap-3">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="toggle-switch" {{ old('is_active', $isEdit ? $appCategory->is_active : true) ? 'checked' : '' }}>
            <span class="form-label mb-0">{{ __('Active') }}</span>
        </label>
    </div>
    <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Toggle whether this category is available for use.') }}</p>
    @error('is_active')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>
