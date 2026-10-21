{{-- Shared App form partial — used by create.blade.php and edit.blade.php --}}
@php
    $isEdit = isset($app) && $app->exists;

    $logoUrl      = $isEdit && ! empty($app->logo_url) ? $app->logo_url : null;
    $existingUrl  = $logoUrl ? asset('storage/'.$logoUrl) : null;
    $existingName = $logoUrl ? basename($logoUrl) : null;

    $edition = old('edition', $app->edition?->value ?? 'full');
@endphp

{{-- Name --}}
<div class="mb-4">
    <label class="form-label" for="name">{{ __('App Name') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $app->name) }}" class="form-input" placeholder="Sales Lite" required>
    @error('name')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    {{-- Category --}}
    <div>
        <label class="form-label" for="category_id">{{ __('Category') }}</label>
        <select id="category_id" name="category_id" class="form-input" required>
            <option value="">{{ __('Select a category') }}</option>
            @foreach($categories as $categoryId => $categoryName)
                <option value="{{ $categoryId }}" @selected((int) old('category_id', $app->category_id) === (int) $categoryId)>{{ $categoryName }}</option>
            @endforeach
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('The Apps Center group it is listed under.') }}</p>
        @error('category_id')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    {{-- Icon --}}
    <div>
        <label class="form-label" for="icon">{{ __('Icon') }}</label>
        <input type="text" id="icon" name="icon" value="{{ old('icon', $app->icon) }}" class="form-input" placeholder="receipt">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('A lucide icon name, shown in the sidebar and the Apps Center.') }}</p>
        @error('icon')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="divider"></div>

{{-- Family, edition and keys: set once, when the app is made --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
    <div>
        <label class="form-label" for="family_key">{{ __('Family Key') }}</label>
        <input type="text" id="family_key" name="family_key" value="{{ old('family_key', $app->family_key) }}" class="form-input" placeholder="sales" @readonly($isEdit)>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Same key for the Lite and Full edition of one app. Used in its URLs, such as /sales/invoices.') }}</p>
        @error('family_key')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="form-label" for="edition">{{ __('Edition') }}</label>
        @if($isEdit)
            <input type="hidden" name="edition" value="{{ $edition }}">
            <input type="text" id="edition" value="{{ $app->editionLabel() }}" class="form-input" readonly>
        @else
            <select id="edition" name="edition" class="form-input">
                @foreach(\App\Enums\Edition::options() as $value => $label)
                    <option value="{{ $value }}" @selected($edition === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        @endif
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Full replaces Lite and keeps its records. An app with no Lite version is Full.') }}</p>
        @error('edition')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="form-label" for="app_key">{{ __('App Key') }}</label>
        <input type="text" id="app_key" name="app_key" value="{{ old('app_key', $app->app_key) }}" class="form-input" placeholder="sales_lite" @readonly($isEdit)>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Unique. Left blank, it is the family key, with _lite for a Lite edition.') }}</p>
        @error('app_key')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>
@if($isEdit)
    <p class="text-xs mb-4 text-[var(--text-muted)]">
        <i data-lucide="lock" class="w-3 h-3 inline-block"></i>
        {{ __('Family, edition and app key are fixed once an app exists: businesses\' rows and records point at them.') }}
    </p>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <label class="form-label" for="sort_order">{{ __('Sort Order') }}</label>
        <input type="number" id="sort_order" name="sort_order" min="0" step="1" value="{{ old('sort_order', $app->sort_order ?? 0) }}" class="form-input" placeholder="0">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Lower comes first in the sidebar and the Apps Center. Shared by both editions of the family.') }}</p>
        @error('sort_order')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="form-label" for="price">{{ __('Price') }}</label>
        <input type="number" id="price" name="price" step="0.01" min="0" value="{{ old('price', $app->price ?? 0) }}" class="form-input" placeholder="0.00">
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Shown on the website. Which plan includes the app is set in Package Access.') }}</p>
        @error('price')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="divider"></div>

{{-- Logo --}}
<div class="mb-4">
    <label class="form-label" for="image">{{ __('Logo') }}</label>
    <x-file-picker
        name="image"
        accept="image/jpeg,image/png,image/svg+xml,image/webp"
        :existing-url="$existingUrl"
        :existing-name="$existingName">
        @error('image')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </x-file-picker>
    <p class="text-xs mt-1 text-[var(--text-muted)]">
        {{ $isEdit ? __('Upload a new logo to replace the current one.') : __('JPG, PNG, SVG or WebP. Max 2MB.') }}
    </p>
</div>

{{-- Description --}}
<div class="mb-4">
    <label class="form-label" for="description">{{ __('Description') }}</label>
    <textarea id="description" name="description" rows="3" class="form-input" placeholder="Quotations, proforma invoices and invoices.">{{ old('description', $app->description) }}</textarea>
    @error('description')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="divider"></div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Status --}}
    <div>
        <label class="form-label" for="status">{{ __('Status') }}</label>
        <select id="status" name="status" class="form-input" required>
            @foreach(\App\Models\Frontend\Application::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $app->status ?? 'available') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Coming Soon is listed in the Apps Center but cannot be turned on yet.') }}</p>
        @error('status')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    {{-- Is Active (toggle switch) --}}
    <div>
        <label class="form-label">{{ __('Listed') }}</label>
        <label class="flex items-center gap-3 cursor-pointer mt-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="toggle-switch" @checked(old('is_active', $app->is_active ?? true))>
            <span class="text-sm">{{ __('Active') }}</span>
        </label>
        <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Inactive apps leave the Apps Center. Businesses that have one on keep it.') }}</p>
        @error('is_active')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>
