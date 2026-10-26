@extends('layouts.admin')
@section('title', __($pageTitle))

@section('content')
    <div class="mb-4 flex justify-end">
        <button type="submit" form="settings-form" class="btn btn-primary" id="save-btn">
            <i data-lucide="save" class="w-4 h-4"></i>
            {{ __('Save All') }}
        </button>
    </div>

    {{-- Tabs --}}
    @php
        $allSections = array_merge(
            array_map(fn($s) => array_merge($s, ['mode' => 'settings']), $settingsSections),
            array_map(fn($s) => array_merge($s, ['mode' => 'items']),    $itemsSections),
        );
        $activeTab = request('tab', $tabOrder[0] ?? 'branding');
    @endphp

    <div class="flex items-center gap-1 mb-4 overflow-x-auto border-b border-[var(--card-border)]">
        @foreach($tabOrder as $slug)
            @php $sec = $allSections[$slug]; @endphp
            <button type="button"
                class="cms-tab flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-all border-b-2 -mb-[1px] {{ $slug === $activeTab ? 'is-active' : '' }}"
                data-tab="{{ $slug }}"
                style="{{ $slug === $activeTab ? 'border-color: var(--primary); color: var(--primary);' : 'border-color: transparent; color: var(--text-muted);' }}">
                <i data-lucide="{{ $sec['icon'] }}" class="w-4 h-4"></i>
                {{ __($sec['label']) }}
            </button>
        @endforeach
    </div>

    {{-- ============================================== --}}
    {{-- SETTINGS-BASED SECTIONS (Hero, CTA, Footer)    --}}
    {{-- ============================================== --}}
    <form id="settings-form" action="{{ route('admin.content.update') }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        @foreach($settingsSections as $slug => $section)
            @if(($section['custom_ui'] ?? null) === 'colors')
                {{-- ── Custom: Appearance / Color Palette UI ── --}}
                @include('admin.content._appearance')
            @else
                {{-- ── Generic settings section ── --}}
                @include('admin.content._settings-section')
            @endif
        @endforeach
    </form>

    {{-- ================================================== --}}
    {{-- ITEMS-BASED SECTIONS (Stats, Features, etc.)       --}}
    {{-- ================================================== --}}
    @foreach($itemsSections as $slug => $section)
        @if(!empty($section['custom_ui']) && $slug === 'footer_columns')
            {{-- ── Custom: Footer Columns UI ── --}}
            @include('admin.content._footer-columns')
        @else
            {{-- ── Generic items section ── --}}
            @include('admin.content._items-section')
        @endif
    @endforeach

    <x-confirm-modal
        id="delete-content-item-modal"
        :title="__('Delete Item?')"
        :message="__('Are you sure you want to delete this item? This action cannot be undone.')"
        action="#"
        method="DELETE"
        :confirm-text="__('Delete')"
        confirm-class="btn-danger"
        icon="trash-2"
    />
@endsection

@push('scripts')
<script>
@include('admin.content._script-items')

@include('admin.content._script-item-fields')

@include('admin.content._script-footer-columns')

@include('admin.content._script-colors')
</script>
@endpush
