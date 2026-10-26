{{-- Content > Appearance: the colour presets and the five colour fields --}}
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
