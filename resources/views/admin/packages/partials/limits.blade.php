{{-- A package's count limits (Doc 01 section 4, Doc 13). Empty is no limit. --}}
@php
    $limits = [
        'business_limit' => [__('Businesses per account'), __('How many businesses on this plan one account may own. New businesses start on Billing Starter, so 1 there means one free business per account.')],
        'user_limit'     => [__('Users per business'), __('The owner and the staff added to the business, together.')],
        'location_limit' => [__('Locations per business'), __('Branches, shops, offices and warehouses, the Main Location included.')],
    ];
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    @foreach ($limits as $field => [$label, $hint])
        <div>
            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
            <input type="number" id="{{ $field }}" name="{{ $field }}" min="1" step="1" value="{{ old($field, $package->{$field}) }}" class="form-input" placeholder="{{ __('No limit') }}">
            @error($field)
                <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
            @enderror
            <p class="text-xs mt-1 text-[var(--text-muted)]">{{ $hint }}</p>
        </div>
    @endforeach
</div>
<p class="text-xs mb-4 text-[var(--text-muted)]">{{ __('A business already over a lower limit keeps everything it has; it only cannot add more.') }}</p>
