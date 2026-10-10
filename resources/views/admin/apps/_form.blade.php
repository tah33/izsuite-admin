{{-- Shared App form partial — used by create.blade.php and edit.blade.php --}}
@php
    $isEdit = isset($app) && $app->exists;

    $logoUrl      = $isEdit && ! empty($app->logo_url) ? $app->logo_url : null;
    $existingUrl  = $logoUrl ? asset('storage/'.$logoUrl) : null;
    $existingName = $logoUrl ? basename($logoUrl) : null;
@endphp

{{-- Name --}}
<div class="mb-4">
    <label class="form-label" for="name">{{ __('App Name') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $isEdit ? $app->name : '') }}" class="form-input" placeholder="Task Manager" required>
    @error('name')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

{{-- Category --}}
<div class="mb-4">
    <label class="form-label" for="category">{{ __('Category') }}</label>
    <select id="category" name="category" class="form-input">
        @unless($isEdit)
            <option value="">{{ __('Select a category') }}</option>
        @endunless
        @foreach($categories as $categoryName)
            <option value="{{ $categoryName }}" {{ old('category', $isEdit ? $app->category : '') == $categoryName ? 'selected' : '' }}>
                {{ $categoryName }}
            </option>
        @endforeach
    </select>
    @error('category')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

{{-- Price --}}
<div class="mb-4">
    <label class="form-label" for="price">{{ __('Price') }}</label>
    <input type="number" id="price" name="price" step="0.01" min="0" value="{{ old('price', $isEdit ? $app->price : 0) }}" class="form-input" placeholder="0.00">
    @error('price')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
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
    <textarea id="description" name="description" rows="4" class="form-input" placeholder="Short description of the app...">{{ old('description', $isEdit ? $app->description : '') }}</textarea>
    @error('description')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="divider"></div>

{{-- Status --}}
<div class="mb-4">
    <label class="form-label" for="status">{{ __('Status') }}</label>
    <select id="status" name="status" class="form-input" required>
        @foreach(\App\Models\Frontend\Application::STATUSES as $value => $label)
            <option value="{{ $value }}" {{ old('status', $isEdit ? $app->status : 'available') === $value ? 'selected' : '' }}>{{ __($label) }}</option>
        @endforeach
    </select>
    @error('status')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

{{-- Is Active (toggle switch) --}}
<div class="mb-4">
    <div class="flex items-center gap-3">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="toggle-switch" {{ old('is_active', $isEdit ? $app->is_active : true) ? 'checked' : '' }}>
            <span class="form-label mb-0">{{ __('Active') }}</span>
        </label>
    </div>
    <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Toggle whether this app is publicly active.') }}</p>
    @error('is_active')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>
