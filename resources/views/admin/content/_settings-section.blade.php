{{-- Content: one landing-page section kept as settings (Hero, CTA, Footer...) --}}
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
