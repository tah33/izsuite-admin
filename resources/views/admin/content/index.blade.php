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
                <div class="cms-panel card p-6 {{ $slug !== $activeTab ? 'hidden' : '' }}" data-tab="{{ $slug }}">
                    <div class="flex items-center gap-2 mb-1">
                        <i data-lucide="palette" class="w-5 h-5 text-[var(--text-primary)]"></i>
                        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('System Colors') }}</h2>
                    </div>
                    <p class="text-xs mb-5 text-[var(--text-muted)]">{{ __('Choose a color preset or customize individual colors') }}</p>

                    {{-- Color presets --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                        @foreach([
                            'emerald' => ['name' => 'Emerald (Default)', 'p' => '160 60% 40%', 'a' => '38 92% 50%'],
                            'ocean'   => ['name' => 'Ocean Blue',        'p' => '210 70% 50%', 'a' => '190 80% 45%'],
                            'purple'  => ['name' => 'Royal Purple',      'p' => '270 60% 55%', 'a' => '290 80% 50%'],
                            'crimson' => ['name' => 'Crimson Red',       'p' => '350 70% 50%', 'a' => '25 90% 55%'],
                            'amber'   => ['name' => 'Amber Gold',        'p' => '38 80% 50%',  'a' => '160 60% 40%'],
                            'slate'   => ['name' => 'Slate Gray',        'p' => '220 20% 45%', 'a' => '210 40% 55%']
                        ] as $slug => $data)
                            <button type="button" class="color-preset-btn" data-preset="{{ $slug }}">
                                <span class="flex gap-1.5 shrink-0">
                                    <span class="preset-dot" style="background: hsl({{ $data['p'] }})"></span>
                                    <span class="preset-dot" style="background: hsl({{ $data['a'] }})"></span>
                                </span>
                                <span class="text-[var(--text-primary)]">{{ $data['name'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Hidden preset name field --}}
                    <input type="hidden" name="content[color_preset]" id="color_preset" value="{{ $settingsValues['color_preset'] ?? 'emerald' }}">

                    {{-- Individual color inputs --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @php
                            $colorFields = [
                                'color_primary'     => ['label' => 'Primary Color',     'default' => '160 60% 40%'],
                                'color_accent'      => ['label' => 'Accent Color',      'default' => '38 92% 50%'],
                                'color_destructive' => ['label' => 'Destructive Color', 'default' => '0 72% 51%'],
                                'color_success'     => ['label' => 'Success Color',     'default' => '160 60% 40%'],
                                'color_warning'     => ['label' => 'Warning Color',     'default' => '38 92% 50%'],
                            ];
                        @endphp
                        @foreach($colorFields as $key => $cf)
                            <div class="form-group flex flex-col gap-2">
                                <label for="{{ $key }}" class="form-label text-sm text-[var(--text-muted)] flex items-center gap-2">
                                    <span class="preset-dot" id="dot-{{ $key }}" style="background: hsl({{ $settingsValues[$key] ?? $cf['default'] }})"></span>
                                    {{ __($cf['label']) }}
                                </label>
                                <input type="text" name="content[{{ $key }}]" id="{{ $key }}" class="form-input color-hsl-input px-3 py-2 border border-[var(--card-border)] rounded-md w-full"
                                       value="{{ $settingsValues[$key] ?? $cf['default'] }}"
                                       placeholder="{{ $cf['default'] }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- ── Generic settings section ── --}}
                <div class="cms-panel card p-6 {{ $slug !== $activeTab ? 'hidden' : '' }}" data-tab="{{ $slug }}">
                    <h2 class="text-lg font-semibold mb-1 text-[var(--text-primary)]">
                        {{ __($section['label'] . ' Section') }}
                    </h2>
                    <p class="text-xs mb-5 text-[var(--text-muted)]">
                        {{ __('Configure the :section section of your landing page', ['section' => strtolower($section['label'])]) }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($section['fields'] as $key => $field)
                            @if($field['type'] === 'image')
                                <div class="form-group span-full">
                                    <label class="form-label">{{ __($field['label']) }}</label>
                                    @if(!empty($field['hint']))
                                        <p class="text-xs mb-2 text-[var(--text-muted)]">{{ $field['hint'] }}</p>
                                    @endif
                                    <div class="image-upload-area">
                                        <div class="image-preview" id="preview-{{ $key }}">
                                            @if(!empty($settingsValues[$key]))
                                                <img src="{{ $settingsValues[$key] }}" alt="{{ $field['label'] }}">
                                            @else
                                                <div class="image-placeholder">
                                                    <i data-lucide="image" class="w-8 h-8 opacity-30"></i>
                                                    <span>{{ __('No image uploaded') }}</span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="image-upload-actions">
                                            <label class="btn btn-outline btn-sm cursor-pointer">
                                                <i data-lucide="upload" class="w-4 h-4"></i>
                                                {{ __('Choose File') }}
                                                <input type="file" name="images[{{ $key }}]" accept="image/*" class="hidden" onchange="previewSettingsImage(this, '{{ $key }}')">
                                            </label>
                                            @if(!empty($settingsValues[$key]))
                                                <span class="text-xs text-[var(--text-muted)]">{{ __('Current') }}: {{ basename($settingsValues[$key]) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @elseif($field['type'] === 'textarea')
                                <div class="form-group span-full">
                                    <label for="{{ $key }}" class="form-label">{{ __($field['label']) }}</label>
                                    <textarea name="content[{{ $key }}]" id="{{ $key }}" class="form-input" rows="3" placeholder="{{ $field['placeholder'] ?? '' }}">{{ $settingsValues[$key] ?? '' }}</textarea>
                                </div>
                            @elseif($field['type'] === 'hidden')
                                {{-- skip hidden fields, we handle them manually --}}
                            @else
                                <div class="form-group {{ $slug === 'seo' ? 'span-full' : '' }}">
                                    <label for="{{ $key }}" class="form-label flex items-center gap-1.5">
                                        @if(!empty($field['icon']))
                                            <i data-lucide="{{ $field['icon'] }}" class="w-4 h-4 text-[var(--text-muted)]"></i>
                                        @endif
                                        {{ __($field['label']) }}
                                    </label>
                                    <input type="text" name="content[{{ $key }}]" id="{{ $key }}" class="form-input" value="{{ $settingsValues[$key] ?? '' }}" placeholder="{{ $field['placeholder'] ?? '' }}">
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </form>

    {{-- ================================================== --}}
    {{-- ITEMS-BASED SECTIONS (Stats, Features, etc.)       --}}
    {{-- ================================================== --}}
    @foreach($itemsSections as $slug => $section)
        @if(!empty($section['custom_ui']) && $slug === 'footer_columns')
            {{-- ── Custom: Footer Columns UI ── --}}
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
        @else
            {{-- ── Generic items section ── --}}
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
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ── Tab Switching ──
document.querySelectorAll('.cms-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const group = tab.dataset.tab;
        document.querySelectorAll('.cms-tab').forEach(t => {
            const isActive = (t === tab);
            t.classList.toggle('is-active', isActive);
            t.style.borderColor = isActive ? 'var(--primary)' : 'transparent';
            t.style.color = isActive ? 'var(--primary)' : 'var(--text-muted)';
        });
        document.querySelectorAll('.cms-panel').forEach(p => p.classList.toggle('hidden', p.dataset.tab !== group));

        // Update URL so refresh stays on current tab
        const url = new URL(window.location);
        url.searchParams.set('tab', group);
        history.replaceState(null, '', url);

        // Show save button only for settings tabs
        const settingsTabs = @json(array_keys($settingsSections));
        document.getElementById('save-btn').style.display = settingsTabs.includes(group) ? '' : 'none';
    });
});

// Initial save button visibility
(function() {
    const settingsTabs = @json(array_keys($settingsSections));
    document.getElementById('save-btn').style.display = settingsTabs.includes('{{ $activeTab }}') ? '' : 'none';
})();

// ── Section field definitions (from PHP) ──
const sectionDefs = @json($itemsSections);

// ── Add Item (generic sections) ──
document.querySelectorAll('.add-item-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const section = btn.dataset.section;
        const def = sectionDefs[section];
        const data = {};
        for (const key of Object.keys(def.fields)) {
            data[key] = '';
        }

        btn.disabled = true;
        try {
            const res = await fetch('/admin/content/items', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ section, data })
            });
            const result = await res.json();
            if (result.success) {
                const url = new URL('/admin/content', window.location.origin);
                url.searchParams.set('tab', section);
                if (@json($context)) url.searchParams.set('context', @json($context));
                location.href = url.toString();
            }
        } catch (e) {
            console.error(e);
        } finally {
            btn.disabled = false;
        }
    });
});

// ── Delete Item ──
let pendingDeleteItem = null;

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.delete-item-btn');
    if (!btn) return;

    const id = btn.dataset.id;
    const section = btn.closest('.cms-panel')?.dataset.tab;
    pendingDeleteItem = { id, section };

    const modal = document.getElementById('delete-content-item-modal');
    const form = modal?.querySelector('form');
    if (form) {
        form.setAttribute('action', '/admin/content/items/' + encodeURIComponent(id));
    }

    if (typeof openConfirmModal === 'function') {
        openConfirmModal('delete-content-item-modal');
    }
});

document.getElementById('delete-content-item-modal')?.querySelector('form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!pendingDeleteItem) return;

    const id = pendingDeleteItem.id;
    const section = pendingDeleteItem.section;

    try {
        const res = await fetch('/admin/content/items/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await res.json();
        if (result.success) {
            const url = new URL('/admin/content', window.location.origin);
            url.searchParams.set('tab', section);
            if (@json($context)) url.searchParams.set('context', @json($context));
            location.href = url.toString();
            return;
        }

        if (window.AdminToast) {
            window.AdminToast.error(result.message || 'Could not delete item.');
        }
    } catch (error) {
        console.error(error);
        if (window.AdminToast) {
            window.AdminToast.error('Could not delete item.');
        }
    } finally {
        if (typeof closeConfirmModal === 'function') {
            closeConfirmModal('delete-content-item-modal');
        }
        pendingDeleteItem = null;
    }
});

// ── Save Item (inline, for generic sections) ──
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.save-item-btn');
    if (!btn) return;

    const card = btn.closest('.item-card');
    const id = card.dataset.id;
    const data = {};

    card.querySelectorAll('[data-field]').forEach(input => {
        data[input.dataset.field] = input.value;
    });

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>';

    try {
        const res = await fetch('/admin/content/items/' + id, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ data })
        });
        const result = await res.json();
        if (result.success) {
            btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5"></i>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(() => {
                btn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 1500);
        }
    } catch (e) {
        console.error(e);
        btn.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5"></i>';
    } finally {
        btn.disabled = false;
    }
});

// ── Settings image preview ──
function previewSettingsImage(input, key) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('preview-' + key);
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Item image upload (AJAX) ──
document.addEventListener('change', async (e) => {
    const input = e.target.closest('.item-image-input');
    if (!input || !input.files[0]) return;

    const card = input.closest('.item-card');
    const field = input.dataset.field;
    const wrapper = input.closest('.image-upload-inline');
    const thumbWrap = wrapper.querySelector('.thumb-wrap');

    const formData = new FormData();
    formData.append('image', input.files[0]);

    try {
        const res = await fetch('/admin/content/upload-image', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            const hidden = card.querySelector(`input[data-field="${field}"]`);
            if (hidden) hidden.value = result.url;
            if (thumbWrap) {
                thumbWrap.innerHTML = `<img src="${result.url}" alt="" class="thumb">`;
            }
        }
    } catch (err) {
        console.error('Upload failed:', err);
    }
});

// ──────────────────────────────────────────
// ── Footer Columns: Custom UI Handlers ──
// ──────────────────────────────────────────

function createLinkRow(title = '', url = '') {
    const row = document.createElement('div');
    row.className = 'col-link-row flex items-center gap-2';
    row.innerHTML = `
        <input type="text" class="form-input flex-1 link-title" value="${title}" placeholder="Link title">
        <input type="text" class="form-input flex-1 link-url" value="${url}" placeholder="URL e.g. /about">
        <button type="button" class="remove-link-btn btn-icon-danger flex-shrink-0" title="Remove">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
        </button>
    `;
    return row;
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.add-link-btn');
    if (!btn) return;
    const card = btn.closest('.footer-column-card');
    const list = card.querySelector('.col-links-list');
    list.appendChild(createLinkRow());
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.remove-link-btn');
    if (!btn) return;
    btn.closest('.col-link-row').remove();
});

document.getElementById('add-column-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('add-column-btn');
    btn.disabled = true;
    try {
        const res = await fetch('/admin/content/items', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ section: 'footer_columns', data: { name: '', links: [] } })
        });
        const result = await res.json();
        if (result.success) {
            const url = new URL('/admin/content', window.location.origin);
            url.searchParams.set('tab', 'footer_columns');
            if (@json($context)) url.searchParams.set('context', @json($context));
            location.href = url.toString();
        }
    } catch (e) {
        console.error(e);
    } finally {
        btn.disabled = false;
    }
});

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.save-column-btn');
    if (!btn) return;

    const card = btn.closest('.footer-column-card');
    const id = card.dataset.id;
    const name = card.querySelector('.col-name-input').value;
    const links = [];

    card.querySelectorAll('.col-link-row').forEach(row => {
        const title = row.querySelector('.link-title').value.trim();
        const url = row.querySelector('.link-url').value.trim();
        if (title || url) {
            links.push({ title, url });
        }
    });

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>';

    try {
        const res = await fetch('/admin/content/items/' + id, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ data: { name, links } })
        });
        const result = await res.json();
        if (result.success) {
            btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5"></i>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            setTimeout(() => {
                btn.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5"></i>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 1500);
        }
    } catch (e) {
        console.error(e);
        btn.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5"></i>';
    } finally {
        btn.disabled = false;
    }
});

// ──────────────────────────────────────────
// ── Color Palette: Presets & HSL Inputs ──
// ──────────────────────────────────────────

const COLOR_PRESETS = {
    emerald: { primary: '160 60% 40%', accent: '38 92% 50%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    ocean:   { primary: '210 70% 50%', accent: '190 80% 45%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    purple:  { primary: '270 60% 55%', accent: '290 80% 50%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    crimson: { primary: '350 70% 50%', accent: '25 90% 55%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    amber:   { primary: '38 80% 50%', accent: '160 60% 40%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
    slate:   { primary: '220 20% 45%', accent: '210 40% 55%', destructive: '0 72% 51%', success: '160 60% 40%', warning: '38 92% 50%' },
};

function updateColorDots() {
    document.querySelectorAll('.color-hsl-input').forEach(input => {
        const dot = document.getElementById('dot-' + input.id);
        if (dot) {
            dot.style.background = `hsl(${input.value})`;
        }
    });
}

function highlightActivePreset(presetName) {
    document.querySelectorAll('.color-preset-btn').forEach(b => {
        const isActive = (b.dataset.preset === presetName);
        if (isActive) {
            b.style.borderColor = 'var(--primary)';
            b.style.color = 'var(--primary)';
            b.style.backgroundColor = 'var(--primary-light)';
            b.style.fontWeight = '600';
        } else {
            b.style.borderColor = '';
            b.style.color = '';
            b.style.backgroundColor = '';
            b.style.fontWeight = '';
        }
    });
}

function applyPreset(presetName) {
    const preset = COLOR_PRESETS[presetName];
    if (!preset) return;

    document.getElementById('color_preset').value = presetName;
    document.getElementById('color_primary').value = preset.primary;
    document.getElementById('color_accent').value = preset.accent;
    document.getElementById('color_destructive').value = preset.destructive;
    document.getElementById('color_success').value = preset.success;
    document.getElementById('color_warning').value = preset.warning;

    highlightActivePreset(presetName);
    updateColorDots();
}

// Preset button clicks
document.querySelectorAll('.color-preset-btn').forEach(btn => {
    btn.addEventListener('click', () => applyPreset(btn.dataset.preset));
});

// Live dot update on input change
document.querySelectorAll('.color-hsl-input').forEach(input => {
    input.addEventListener('input', updateColorDots);
});

// Highlight active preset on load
(function() {
    updateColorDots();
    const current = document.getElementById('color_preset')?.value || 'emerald';
    highlightActivePreset(current);
})();
</script>
@endpush
