{{--
    x-file-picker — styled file input that replaces the browser default.

    Props:
      name          string   — form field name
      accept        string   — MIME / extension filter  (default: image/*)
      hint          string   — small helper text below the filename
      existing-url  string   — URL of an already-stored file (shows preview)
      existing-name string   — display name for an already-stored file
      required      bool     — marks the hidden native input as required
      label         string   — optional label rendered above the picker
--}}
@props([
    'name',
    'accept'       => 'image/*',
    'hint'         => null,
    'existingUrl'  => null,
    'existingName' => null,
    'required'     => false,
    'label'        => null,
])

@php
    $uid      = 'fp-' . Str::slug($name, '-') . '-' . substr(md5($name . microtime()), 0, 6);
    $isImage  = str_contains($accept, 'image');
    $hasFile  = !empty($existingUrl) || !empty($existingName);
    $dispName = $existingName ?? ($existingUrl ? basename($existingUrl) : null);
    $imgHint  = $hint ?? ($isImage ? 'JPG, PNG, SVG or WebP · max 5 MB' : 'Max 10 MB');
@endphp

@if($label)
    <label class="form-label">{{ $label }}</label>
@endif

<div data-fp="{{ $uid }}" class="flex items-start gap-4">

    {{-- ── Drop-zone / preview square ─────────────────────────────── --}}
    <button type="button" data-fp-zone
        class="group relative flex items-center justify-center rounded-lg border-2 border-dashed border-[var(--card-border)] bg-[var(--content-bg)] overflow-hidden transition hover:border-[var(--primary)] hover:bg-[var(--primary-light)] focus:outline-none focus:ring-2 focus:ring-[var(--primary)]
               {{ $isImage ? 'w-28 h-28 shrink-0' : 'w-20 h-20 shrink-0' }}">

        @if($isImage)
            {{-- Image preview --}}
            <img data-fp-img src="{{ $existingUrl ?? '' }}" alt=""
                class="absolute inset-0 w-full h-full object-contain bg-white {{ $existingUrl ? '' : 'hidden' }}">

            {{-- Empty state --}}
            <div data-fp-empty class="flex flex-col items-center gap-1 text-[var(--text-muted)] {{ $existingUrl ? 'hidden' : '' }}">
                <i data-lucide="image-plus" class="w-7 h-7"></i>
                <span class="text-xs leading-none">{{ __('Click to upload') }}</span>
            </div>

            {{-- Hover overlay when image exists --}}
            <div data-fp-overlay
                class="absolute inset-0 {{ $existingUrl ? '' : '!hidden' }} hidden group-hover:flex items-center justify-center bg-black/40 text-white text-xs font-medium gap-1">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> {{ __('Change') }}
            </div>

        @else
            {{-- File icon (CSV, PDF, etc.) --}}
            <div data-fp-empty class="flex flex-col items-center gap-1 text-[var(--text-muted)] {{ $hasFile ? 'hidden' : '' }}">
                <i data-lucide="file-up" class="w-7 h-7"></i>
                <span class="text-xs leading-none">{{ __('Browse') }}</span>
            </div>
            <div data-fp-file-icon class="flex flex-col items-center gap-1 text-[var(--primary)] {{ $hasFile ? '' : 'hidden' }}">
                <i data-lucide="file-check" class="w-7 h-7"></i>
                <span class="text-xs leading-none">{{ __('Ready') }}</span>
            </div>
        @endif

    </button>

    {{-- ── Right column ──────────────────────────────────────────── --}}
    <div class="flex-1 min-w-0">
        <p data-fp-name class="text-sm font-medium text-[var(--text-primary)] truncate">
            {{ $hasFile ? $dispName : __('No file selected') }}
        </p>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ $imgHint }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" data-fp-pick class="btn btn-secondary btn-sm">
                <i data-lucide="upload" class="w-4 h-4"></i>
                <span data-fp-pick-label>{{ $hasFile ? __('Replace') : __('Choose file') }}</span>
            </button>

            @if($isImage)
                {{-- !hidden, not hidden: .btn's display would win over a plain utility. --}}
                <button type="button" data-fp-clear
                    class="btn btn-sm text-[var(--danger)] {{ $hasFile ? '' : '!hidden' }}">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> {{ __('Remove') }}
                </button>
            @endif
        </div>

        {{-- Error slot --}}
        {{ $slot }}
    </div>

    {{-- Hidden native input --}}
    <input type="file"
           id="{{ $uid }}-input"
           name="{{ $name }}"
           accept="{{ $accept }}"
           class="hidden"
           @if($required) required @endif>

    @if($isImage)
        <input type="hidden" name="remove_{{ $name }}" data-fp-remove value="0">
    @endif

</div>

<script>
(function () {
    var root  = document.querySelector('[data-fp="{{ $uid }}"]');
    if (!root) return;

    var zone      = root.querySelector('[data-fp-zone]');
    var input     = root.querySelector('#{{ $uid }}-input');
    var nameEl    = root.querySelector('[data-fp-name]');
    var pickBtn   = root.querySelector('[data-fp-pick]');
    var pickLabel = root.querySelector('[data-fp-pick-label]');
    var clearBtn  = root.querySelector('[data-fp-clear]');
    var emptyEl   = root.querySelector('[data-fp-empty]');

    // Image-specific
    var imgEl     = root.querySelector('[data-fp-img]');
    var overlay   = root.querySelector('[data-fp-overlay]');
    var removeFlag= root.querySelector('[data-fp-remove]');

    // File-specific
    var fileIcon  = root.querySelector('[data-fp-file-icon]');

    var REPLACE = '{{ __("Replace") }}';
    var CHOOSE  = '{{ __("Choose file") }}';
    var EMPTY   = '{{ __("No file selected") }}';

    function open() { input.click(); }
    zone.addEventListener('click', open);
    pickBtn.addEventListener('click', open);

    // Drag-over highlight
    zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('border-[var(--primary)]'); });
    zone.addEventListener('dragleave', function () { zone.classList.remove('border-[var(--primary)]'); });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('border-[var(--primary)]');
        if (e.dataTransfer.files.length) {
            var dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        }
    });

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;

        nameEl.textContent  = file.name;
        pickLabel.textContent = REPLACE;
        if (removeFlag) removeFlag.value = '0';

        if (imgEl) {
            var reader = new FileReader();
            reader.onload = function (e) {
                imgEl.src = e.target.result;
                imgEl.classList.remove('hidden');
                if (emptyEl) emptyEl.classList.add('hidden');
                if (overlay) { overlay.classList.remove('!hidden'); }
            };
            reader.readAsDataURL(file);
        }

        if (fileIcon) {
            fileIcon.classList.remove('hidden');
            if (emptyEl) emptyEl.classList.add('hidden');
        }

        if (clearBtn) clearBtn.classList.remove('!hidden');
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            nameEl.textContent = EMPTY;
            pickLabel.textContent = CHOOSE;
            clearBtn.classList.add('!hidden');
            if (removeFlag) removeFlag.value = '1';
            if (imgEl) { imgEl.src = ''; imgEl.classList.add('hidden'); }
            if (emptyEl) emptyEl.classList.remove('hidden');
            if (overlay) overlay.classList.add('!hidden');
            if (fileIcon) fileIcon.classList.add('hidden');
        });
    }
})();
</script>
