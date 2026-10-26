{{-- Shared Menu Builder form — used by create.blade.php and edit.blade.php --}}
@php
    $section = old('section', $item->section);
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    {{-- Where it lives --}}
    <div>
        <label class="form-label" for="section">{{ __('Lives in') }}</label>
        <select id="section" name="section" class="form-input" required>
            @foreach(\App\Models\Frontend\MenuItem::SECTIONS as $value => $label)
                <option value="{{ $value }}" @selected($section === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        @error('section')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div data-apps-only>
        <label class="form-label" for="family_key">{{ __('App') }}</label>
        <select id="family_key" name="family_key" class="form-input">
            <option value="">{{ __('Choose an app') }}</option>
            @foreach($families as $familyKey => $familyName)
                <option value="{{ $familyKey }}" @selected(old('family_key', $item->family_key) === $familyKey)>{{ $familyName }} ({{ $familyKey }})</option>
            @endforeach
        </select>
        @error('family_key')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div data-apps-only>
        <label class="form-label" for="min_edition">{{ __('Minimum edition') }}</label>
        <select id="min_edition" name="min_edition" class="form-input">
            <option value="lite" @selected(old('min_edition', $item->min_edition?->value ?? 'lite') === 'lite')>{{ __('Lite - shown in Lite and Full') }}</option>
            <option value="full" @selected(old('min_edition', $item->min_edition?->value) === 'full')>{{ __('Full - shown in Full only') }}</option>
        </select>
        @error('min_edition')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label class="form-label" for="label">{{ __('Label') }}</label>
        <input type="text" id="label" name="label" value="{{ old('label', $item->label) }}" class="form-input" placeholder="Invoices" required>
        @error('label')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="icon">{{ __('Icon') }}</label>
        <input type="text" id="icon" name="icon" value="{{ old('icon', $item->icon) }}" class="form-input" placeholder="file-text">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('A lucide icon name.') }}</p>
        @error('icon')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="route">{{ __('Route') }}</label>
        <input type="text" id="route" name="route" value="{{ old('route', $item->route) }}" class="form-input" placeholder="/sales/invoices">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('The frontend page it opens. An app\'s items start with its family key: /sales/...') }}</p>
        @error('route')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="parent_id">{{ __('Parent') }}</label>
        <select id="parent_id" name="parent_id" class="form-input">
            <option value="">{{ __('None (top level)') }}</option>
            @foreach($parents as $parent)
                <option value="{{ $parent->id }}" @selected((int) old('parent_id', $item->parent_id) === $parent->id)>
                    {{ $parent->family_key ?: __(\App\Models\Frontend\MenuItem::SECTIONS[$parent->section] ?? $parent->section) }} › {{ $parent->label }}
                </option>
            @endforeach
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('A top-level item in the same place. One level of nesting.') }}</p>
        @error('parent_id')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="divider"></div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div>
        <label class="form-label" for="permission_key">{{ __('Permission') }}</label>
        <input type="text" id="permission_key" name="permission_key" value="{{ old('permission_key', $item->permission_key) }}" class="form-input" placeholder="sales.invoice.view">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Optional. Business owners see every item; staff roles will use this.') }}</p>
        @error('permission_key')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="show_if">{{ __('Show if') }}</label>
        <select id="show_if" name="show_if" class="form-input">
            <option value="">{{ __('Always') }}</option>
            @foreach($showIf as $key => $label)
                <option value="{{ $key }}" @selected(old('show_if', $item->show_if) === $key)>{{ __($label) }}</option>
            @endforeach
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Rules are defined in code; pick one.') }}</p>
        @error('show_if')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="sort_order">{{ __('Order') }}</label>
        <input type="number" id="sort_order" name="sort_order" min="0" step="1" value="{{ old('sort_order', $item->sort_order ?? 0) }}" class="form-input">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Or drag it in the Menu Builder.') }}</p>
        @error('sort_order')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<label class="flex items-center gap-3 cursor-pointer">
    <input type="checkbox" name="is_active" value="1" class="toggle-switch" @checked(old('is_active', $item->is_active ?? true))>
    <span class="form-label mb-0">{{ __('Shown') }}</span>
</label>
<p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Switch off to hide the item without deleting it.') }}</p>

@push('scripts')
<script>
    // Family and edition only mean something for an app's items. A jQuery
    // listener: the select is a Select2, which announces a pick through jQuery.
    (function () {
        var section = document.getElementById('section');
        var toggle = function () {
            document.querySelectorAll('[data-apps-only]').forEach(function (el) {
                el.style.display = section.value === 'apps' ? '' : 'none';
            });
        };
        $(section).on('change', toggle);
        toggle();
    })();
</script>
@endpush
