{{-- Shared form for offline payment methods --}}
{{-- Summernote CSS --}}
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">

<div class="form-group">
    <label for="name" class="form-label">{{ __('Method Name') }} <span class="text-red-400">*</span></label>
    <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $method->name ?? '') }}" required placeholder="{{ __('e.g. Bank Transfer') }}">
    @error('name') <span class="form-error">{{ $message }}</span> @enderror
</div>

<div class="form-group">
    <label for="description" class="form-label">{{ __('Description') }}</label>
    <input type="text" name="description" id="description" class="form-input" value="{{ old('description', $method->description ?? '') }}" placeholder="{{ __('Short description for admin reference') }}">
</div>

{{-- Image Upload --}}
<div class="mb-4">
    <x-file-picker
        name="image"
        accept="image/jpeg,image/png,image/svg+xml,image/webp"
        :label="__('Method Image / Logo')"
        :hint="__('JPG, PNG, SVG, or WebP · max 2 MB')"
        :existing-url="!empty($method->logo_url ?? null) ? asset('storage/'.$method->logo_url) : null"
        :existing-name="!empty($method->logo_url ?? null) ? basename($method->logo_url) : null">
        @error('image') <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p> @enderror
    </x-file-picker>
</div>

{{-- WYSIWYG Instructions --}}
<div class="form-group">
    <label for="instructions" class="form-label">{{ __('Payment Instructions') }}</label>
    <textarea name="instructions" id="summernote-instructions" class="form-input">{{ old('instructions', $method->instructions ?? '') }}</textarea>
    <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Rich text instructions shown to users when they select this method at checkout.') }}</p>
    @error('instructions') <span class="form-error">{{ $message }}</span> @enderror
</div>

@include('admin.payment-methods._countries', ['method' => $method ?? null, 'hint' => __('By the business\'s billing country. Invoices of businesses billed elsewhere leave its instructions out.')])

<div class="grid grid-cols-2 gap-4">
    <div class="form-group">
        <label for="sort_order" class="form-label">{{ __('Sort Order') }}</label>
        <input type="number" name="sort_order" id="sort_order" class="form-input" value="{{ old('sort_order', $method->sort_order ?? 0) }}" min="0">
    </div>
    <div class="form-group flex items-end">
        <label class="flex items-center gap-2 cursor-pointer pb-2">
            <input type="checkbox" name="is_active" value="1" class="form-checkbox" {{ old('is_active', $method->is_active ?? false) ? 'checked' : '' }}>
            <span class="text-sm font-medium text-[var(--text-primary)]">{{ __('Active') }}</span>
        </label>
    </div>
</div>

{{-- Switched on, a method takes real payments: changing it is a critical action (Doc 15 section 15.25). --}}
@include('admin.partials.critical-confirm', [
    'impact' => ($method ?? null)?->takesRealPayments()
        ? __('This method takes real payments: businesses see its instructions on every invoice they pay by it.')
        : __('Saving it switched on makes it take real payments: businesses then see its instructions on their invoices.'),
    'class'  => 'mt-4',
])

{{-- Summernote JS --}}
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
    $(function() {
        $('#summernote-instructions').summernote({
            height: 200,
            placeholder: '{{ __("Enter payment instructions...") }}',
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'strikethrough']],
                ['font', ['superscript', 'subscript']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'table', 'hr']],
                ['misc', ['fullscreen', 'codeview', 'undo', 'redo']],
            ],
        });
    });
</script>



