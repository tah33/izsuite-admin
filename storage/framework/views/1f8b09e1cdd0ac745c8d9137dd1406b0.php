<?php $__env->startSection('title', __('Settings')); ?>

<?php $__env->startSection('content'); ?>

    
    <?php if($errors->any()): ?>
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            <?php echo e(__('Please fix the highlighted settings and try again.')); ?>

        </div>
    <?php endif; ?>

    
    <?php
        $groups = array_keys($grouped);
        
        // Find the first group that has an error
        $firstErrorGroup = null;
        foreach($grouped as $group => $settings) {
            foreach($settings as $setting) {
                if ($errors->has('settings.' . $setting['key'])) {
                    $firstErrorGroup = $group;
                    break 2;
                }
            }
        }
        
        $activeTab = $firstErrorGroup ?: request('tab', session('active_tab', $groups[0] ?? 'general'));

        // Auto-generated labels read badly for the SMTP keys ("Smtp from address"),
        // so anything listed here overrides the humanised key.
        $labels = [
            'smtp_enabled'      => __('Use custom SMTP'),
            'smtp_host'         => __('SMTP host'),
            'smtp_port'         => __('SMTP port'),
            'smtp_encryption'   => __('Encryption'),
            'smtp_username'     => __('SMTP username'),
            'smtp_password'     => __('SMTP password'),
            'smtp_from_address' => __('From address'),
            'smtp_from_name'    => __('From name'),
        ];

        $helpTexts = [
            'smtp_enabled'      => __('When off, the app falls back to the mailer configured in .env. Your credentials below are kept either way.'),
            'smtp_host'         => __('e.g. smtp.gmail.com, smtp.mailgun.org, smtp-relay.brevo.com'),
            'smtp_port'         => __('587 for TLS, 465 for SSL, 25 for unencrypted.'),
            'smtp_username'     => __('Usually the full email address of the sending account.'),
            'smtp_password'     => __('Leave blank to keep the saved password. Gmail requires an App Password, not your account password.'),
            'smtp_from_address' => __('The address recipients will see in the From field.'),
            'smtp_from_name'    => __('The name recipients will see, e.g. your site name.'),
        ];

        $requiredKeys = ['ai_active_provider', 'ai_temperature', 'ai_max_tokens', 'smtp_host', 'smtp_port', 'smtp_from_address'];
    ?>

    <div class="flex items-center gap-1 mb-4 border-b border-[var(--card-border)] overflow-x-auto">
        <?php $__currentLoopData = $grouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $settings): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $hasError = false;
                foreach($settings as $setting) {
                    if ($errors->has('settings.' . $setting['key'])) {
                        $hasError = true;
                        break;
                    }
                }
            ?>
            <button type="button"
                class="settings-tab relative px-4 py-2 text-sm font-medium rounded-t-lg flex items-center gap-2 whitespace-nowrap <?php echo e($group === $activeTab ? 'active' : ''); ?>"
                data-tab="<?php echo e($group); ?>">
                <?php echo e(str_replace('_', ' ', ucfirst($group))); ?>

                <?php if($hasError): ?>
                    <span class="w-2 h-2 rounded-full bg-[var(--danger)] shadow-[0_0_8px_var(--danger)]"></span>
                <?php endif; ?>
            </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

        <?php $__currentLoopData = $grouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $settings): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <form action="<?php echo e(route('admin.settings.update')); ?>" method="POST" enctype="multipart/form-data" novalidate>
                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <input type="hidden" name="_group" value="<?php echo e($group); ?>">

                <div class="settings-panel card p-6 mb-4 <?php echo e($group !== $activeTab ? 'hidden' : ''); ?>" data-tab="<?php echo e($group); ?>">
                    <h2 class="text-lg font-semibold mb-4 text-[var(--text-primary)]"><?php echo e(str_replace('_', ' ', ucfirst($group))); ?> <?php echo e(__('Settings')); ?></h2>

                    <div class="space-y-4">
                        <?php $__currentLoopData = $settings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $setting): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $fieldValue = old('settings.' . $setting['key'], $setting['value']);
                                $fieldError = $errors->first('settings.' . $setting['key']);
                                $providerScope = null;

                                if (str_starts_with($setting['key'], 'ai_openai_')) {
                                    $providerScope = 'openai';
                                }

                                if (str_starts_with($setting['key'], 'ai_gemini_')) {
                                    $providerScope = 'gemini';
                                }
                            ?>

                            <div class="form-group <?php echo e($providerScope ? 'ai-provider-field' : ''); ?>" <?php if($providerScope): ?> data-provider="<?php echo e($providerScope); ?>" <?php endif; ?>>
                                <label for="setting_<?php echo e($setting['key']); ?>" class="form-label">
                                    <?php echo e($labels[$setting['key']] ?? str_replace('_', ' ', ucfirst(str_replace($group . '_', '', $setting['key'])))); ?>

                                    <?php if(in_array($setting['key'], $requiredKeys)): ?> <span class="text-[var(--danger)]">*</span> <?php endif; ?>
                                </label>

                                <?php if($setting['key'] === 'default_currency'): ?>
                                    <select name="settings[<?php echo e($setting['key']); ?>]" id="setting_<?php echo e($setting['key']); ?>" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>">
                                        <?php $__currentLoopData = \App\Models\Admin\Currency::active()->orderBy('name')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $curr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($curr->code); ?>" <?php echo e($fieldValue === $curr->code ? 'selected' : ''); ?>>
                                                <?php echo e($curr->name); ?> (<?php echo e($curr->symbol); ?> / <?php echo e($curr->code); ?>)
                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                <?php elseif($setting['key'] === 'default_language'): ?>
                                    <select name="settings[<?php echo e($setting['key']); ?>]" id="setting_<?php echo e($setting['key']); ?>" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>">
                                        <?php $__currentLoopData = \App\Models\Admin\Language::active()->orderBy('name')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lang): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($lang->code); ?>" <?php echo e($fieldValue === $lang->code ? 'selected' : ''); ?>>
                                                <?php echo e($lang->name); ?> (<?php echo e($lang->code); ?>)
                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                <?php elseif($setting['key'] === 'ai_active_provider'): ?>
                                    <select name="settings[<?php echo e($setting['key']); ?>]" id="setting_ai_active_provider" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>">
                                        <option value="openai" <?php echo e($fieldValue === 'openai' ? 'selected' : ''); ?>>OpenAI</option>
                                        <option value="gemini" <?php echo e($fieldValue === 'gemini' ? 'selected' : ''); ?>>Gemini</option>
                                    </select>
                                <?php elseif($setting['key'] === 'smtp_encryption'): ?>
                                    <select name="settings[<?php echo e($setting['key']); ?>]" id="setting_<?php echo e($setting['key']); ?>" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>">
                                        <option value="tls" <?php echo e($fieldValue === 'tls' ? 'selected' : ''); ?>><?php echo e(__('TLS / STARTTLS')); ?></option>
                                        <option value="ssl" <?php echo e($fieldValue === 'ssl' ? 'selected' : ''); ?>><?php echo e(__('SSL')); ?></option>
                                        <option value="none" <?php echo e($fieldValue === 'none' ? 'selected' : ''); ?>><?php echo e(__('None')); ?></option>
                                    </select>
                                <?php elseif($setting['key'] === 'smtp_password'): ?>
                                    
                                    <input
                                        type="password"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>"
                                        value=""
                                        placeholder="<?php echo e($setting['value'] ? str_repeat(chr(0xE2).chr(0x80).chr(0xA2), 8).'  '.__('saved') : ''); ?>"
                                        autocomplete="new-password"
                                    >
                                <?php elseif($setting['key'] === 'smtp_port'): ?>
                                    <input
                                        type="number"
                                        min="1"
                                        max="65535"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>"
                                        value="<?php echo e($fieldValue); ?>"
                                    >
                                <?php elseif($setting['key'] === 'smtp_from_address'): ?>
                                    <input
                                        type="email"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>"
                                        value="<?php echo e($fieldValue); ?>"
                                        placeholder="no-reply@example.com"
                                    >
                                <?php elseif(str_ends_with($setting['key'], '_enabled')): ?>
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="hidden" name="settings[<?php echo e($setting['key']); ?>]" value="0">
                                        <input type="checkbox" name="settings[<?php echo e($setting['key']); ?>]" value="1" class="w-4 h-4 rounded ai-enabled-input" data-key="<?php echo e($setting['key']); ?>" <?php echo e((string) $fieldValue === '1' ? 'checked' : ''); ?>>
                                        <span class="text-sm font-medium text-[var(--text-primary)]"><?php echo e(__('Enabled')); ?></span>
                                    </label>
                                <?php elseif(str_contains($setting['key'], 'api_key') || str_contains($setting['key'], 'client_secret')): ?>
                                    <input
                                        type="password"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?> <?php echo e(str_contains($setting['key'], 'ai_') ? 'ai-conditional-input' : ''); ?>"
                                        data-key="<?php echo e($setting['key']); ?>"
                                        value="<?php echo e($fieldValue); ?>"
                                        autocomplete="off"
                                    >
                                <?php elseif(in_array($setting['key'], ['ai_openai_model', 'ai_gemini_model'])): ?>
                                    <input
                                        type="text"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?> ai-conditional-input"
                                        data-key="<?php echo e($setting['key']); ?>"
                                        value="<?php echo e($fieldValue); ?>"
                                    >
                                <?php elseif(in_array($setting['key'], ['ai_temperature', 'ai_max_tokens'])): ?>
                                    <input
                                        type="text"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>"
                                        value="<?php echo e($fieldValue); ?>"
                                    >
                                <?php elseif($setting['key'] === 'site_logo'): ?>
                                    <div class="flex items-center gap-4">
                                        <?php if($fieldValue): ?>
                                            <div class="w-16 h-16 rounded border bg-gray-50 flex items-center justify-center overflow-hidden">
                                                <img src="<?php echo e(app(\App\Services\Support\ImageService::class)->publicUrl($fieldValue)); ?>" class="max-w-full max-h-full object-contain">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-16 h-16 rounded border bg-gray-50 flex items-center justify-center text-gray-400">
                                                 <?php if (isset($component)) { $__componentOriginal0e3e854f1972cb532cc8b5bc0ace80b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0e3e854f1972cb532cc8b5bc0ace80b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.site-logo','data' => ['class' => 'w-8 h-8']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('site-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-8 h-8']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0e3e854f1972cb532cc8b5bc0ace80b0)): ?>
<?php $attributes = $__attributesOriginal0e3e854f1972cb532cc8b5bc0ace80b0; ?>
<?php unset($__attributesOriginal0e3e854f1972cb532cc8b5bc0ace80b0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0e3e854f1972cb532cc8b5bc0ace80b0)): ?>
<?php $component = $__componentOriginal0e3e854f1972cb532cc8b5bc0ace80b0; ?>
<?php unset($__componentOriginal0e3e854f1972cb532cc8b5bc0ace80b0); ?>
<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1">
                                            <input type="file" name="settings[<?php echo e($setting['key']); ?>]" id="setting_<?php echo e($setting['key']); ?>" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>" accept="image/*">
                                            <p class="text-xs text-[var(--text-muted)] mt-1"><?php echo e(__('Recommended size: 200x200px. Max: 2MB.')); ?></p>
                                        </div>
                                    </div>
                                <?php elseif($setting['key'] === 'site_favicon'): ?>
                                    <div class="flex items-center gap-4">
                                        <?php if($fieldValue): ?>
                                            <div class="w-10 h-10 rounded border bg-gray-50 flex items-center justify-center overflow-hidden">
                                                <img src="<?php echo e(app(\App\Services\Support\ImageService::class)->publicUrl($fieldValue)); ?>" class="max-w-full max-h-full object-contain">
                                            </div>
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded border bg-gray-50 flex items-center justify-center text-gray-400">
                                                <i data-lucide="image" class="w-5 h-5"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1">
                                            <input type="file" name="settings[<?php echo e($setting['key']); ?>]" id="setting_<?php echo e($setting['key']); ?>" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>" accept="image/*,.ico">
                                            <p class="text-xs text-[var(--text-muted)] mt-1"><?php echo e(__('Recommended: 32×32px PNG or ICO.')); ?></p>
                                        </div>
                                    </div>
                                <?php elseif($setting['key'] === 'primary_color'): ?>
                                    <div class="flex items-center gap-3">
                                        <input type="color" name="settings[<?php echo e($setting['key']); ?>]" id="setting_<?php echo e($setting['key']); ?>" class="w-10 h-10 rounded-lg p-1 bg-white border border-[var(--card-border)] cursor-pointer" value="<?php echo e($fieldValue); ?>">
                                        <input type="text" value="<?php echo e($fieldValue); ?>" class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?> flex-1 font-mono text-sm" placeholder="#000000" oninput="this.previousElementSibling.value = this.value" onchange="this.previousElementSibling.value = this.value">
                                    </div>
                                <?php elseif(str_contains($setting['key'], 'description') || str_contains($setting['key'], 'address') || $setting['key'] === 'footer_text'): ?>
                                    <textarea
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>"
                                        rows="3"
                                    ><?php echo e($fieldValue); ?></textarea>
                                <?php else: ?>
                                    <input
                                        type="text"
                                        name="settings[<?php echo e($setting['key']); ?>]"
                                        id="setting_<?php echo e($setting['key']); ?>"
                                        class="form-input <?php echo e($fieldError ? 'form-input-error' : ''); ?>"
                                        value="<?php echo e($fieldValue); ?>"
                                    >
                                <?php endif; ?>

                                <?php if(isset($helpTexts[$setting['key']])): ?>
                                    <p class="text-xs text-[var(--text-muted)] mt-1"><?php echo e($helpTexts[$setting['key']]); ?></p>
                                <?php endif; ?>

                                <?php if($fieldError): ?>
                                    <p class="form-error" style="color: #ef4444; display: flex; align-items: center; gap: 4px;">
                                        <i data-lucide="alert-circle" class="w-3.5 h-3.5" style="color: #ef4444; margin: 0;"></i>
                                        <span style="font-size: 0.7rem; font-weight: 500;"><?php echo e($fieldError); ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <div class="mt-8 pt-6 border-t border-[var(--card-border)]">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <?php echo e(__('Save')); ?> <?php echo e(str_replace('_', ' ', ucfirst($group))); ?> <?php echo e(__('Settings')); ?>

                        </button>
                    </div>
                </div>
            </form>

            
            <?php if($group === 'mail'): ?>
                <form action="<?php echo e(route('admin.settings.test-mail')); ?>" method="POST" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="settings-panel card p-6 mb-4 <?php echo e($group !== $activeTab ? 'hidden' : ''); ?>" data-tab="<?php echo e($group); ?>">
                        <h2 class="text-lg font-semibold mb-1 text-[var(--text-primary)]"><?php echo e(__('Send a test email')); ?></h2>
                        <p class="text-sm text-[var(--text-muted)] mb-4">
                            <?php echo e(__('Save your settings first, then send a test message to confirm the credentials work.')); ?>

                        </p>

                        <div class="flex items-center gap-2 mb-4 text-xs">
                            <span class="text-[var(--text-muted)]"><?php echo e(__('Active mailer')); ?>:</span>
                            <span class="px-2 py-0.5 rounded-full font-mono font-medium <?php echo e(config('mail.default') === 'smtp' ? 'bg-[var(--success-light)] text-[var(--primary-dark)]' : 'bg-[var(--danger-light)] text-[var(--danger)]'); ?>">
                                <?php echo e(config('mail.default')); ?>

                            </span>
                            <?php if(config('mail.default') !== 'smtp'): ?>
                                <span class="text-[var(--text-muted)]"><?php echo e(__('- turn on "Use custom SMTP" above to send through your own server.')); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="test_email" class="form-label"><?php echo e(__('Send to')); ?> <span class="text-[var(--danger)]">*</span></label>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <input
                                    type="email"
                                    name="test_email"
                                    id="test_email"
                                    class="form-input flex-1 <?php echo e($errors->first('test_email') ? 'form-input-error' : ''); ?>"
                                    value="<?php echo e(old('test_email', auth()->user()?->email)); ?>"
                                    placeholder="you@example.com"
                                    required
                                >
                                <button type="submit" class="btn btn-primary whitespace-nowrap">
                                    <i data-lucide="send" class="w-4 h-4"></i>
                                    <?php echo e(__('Send Test Email')); ?>

                                </button>
                            </div>

                            <?php if($errors->first('test_email')): ?>
                                <p class="form-error" style="color: #ef4444; display: flex; align-items: center; gap: 4px;">
                                    <i data-lucide="alert-circle" class="w-3.5 h-3.5" style="color: #ef4444; margin: 0;"></i>
                                    <span style="font-size: 0.7rem; font-weight: 500;"><?php echo e($errors->first('test_email')); ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.querySelectorAll('.settings-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const group = tab.dataset.tab;

            document.querySelectorAll('.settings-tab').forEach(t => t.classList.toggle('active', t === tab));
            document.querySelectorAll('.settings-panel').forEach(panel => {
                panel.classList.toggle('hidden', panel.dataset.tab !== group);
            });

            const url = new URL(window.location);
            url.searchParams.set('tab', group);
            history.replaceState(null, '', url);
        });
    });

    function syncAiSettingsVisibility() {
        const providerSelect = document.getElementById('setting_ai_active_provider');
        if (!providerSelect) return;

        const activeProvider = providerSelect.value;

        document.querySelectorAll('.ai-provider-field').forEach(field => {
            const provider = field.dataset.provider;
            const isVisible = provider === activeProvider;
            field.classList.toggle('hidden', !isVisible);
        });
    }

    document.getElementById('setting_ai_active_provider')?.addEventListener('change', syncAiSettingsVisibility);
    syncAiSettingsVisibility();
</script>
<?php $__env->stopPush(); ?>


<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\izsuite-admin\resources\views/admin/settings/index.blade.php ENDPATH**/ ?>