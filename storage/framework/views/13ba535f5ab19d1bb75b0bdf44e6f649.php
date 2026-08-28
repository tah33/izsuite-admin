<?php
    $activeLocale = session('admin_locale', setting('default_language', 'en'));
    $activeCurrency = session('admin_currency', setting('default_currency', 'USD'));
    $activeCurrModel = \App\Models\Admin\Currency::where('code', $activeCurrency)->first();
    $perPageOptions = [10, 15, 25, 50, 100];
    $currentPerPage = (int) request()->integer('per_page', 15);
    if (! in_array($currentPerPage, $perPageOptions, true)) {
        $currentPerPage = 15;
    }
?>
<header class="topbar sticky top-0 z-30">
    <div class="flex items-center gap-3">
        <button id="sidebar-toggle" class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100">
            <i data-lucide="menu" class="w-5 h-5 text-[var(--text-secondary)]"></i>
        </button>
        <span class="topbar-title"><?php echo e($panelTitle ?? ''); ?></span>
    </div>

    <div class="topbar-actions">
        <form method="GET" action="<?php echo e(url()->current()); ?>" class="hidden xl:flex items-center gap-2">
            <?php $__currentLoopData = request()->except(['per_page', 'page']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(! is_array($value)): ?>
                    <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <label class="text-xs font-medium text-[var(--text-muted)]"><?php echo e(__('Rows')); ?></label>
            <select name="per_page" onchange="this.form.submit()" class="h-9 rounded-lg border border-[var(--card-border)] bg-white px-2 text-sm text-[var(--text-primary)]">
                <?php $__currentLoopData = $perPageOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option); ?>" <?php echo e($currentPerPage === $option ? 'selected' : ''); ?>>
                        <?php echo e($option); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </form>

        <a href="<?php echo e(config('app.frontend_url')); ?>" target="_blank" rel="noopener noreferrer" class="topbar-dropdown" title="<?php echo e(__('Visit Website')); ?>">
            <i data-lucide="globe" class="w-4 h-4"></i>
        </a>
        <a href="<?php echo e(route('admin.cache-clear')); ?>" class="topbar-dropdown text-[var(--warning)] hover:bg-[var(--warning-light)]" title="<?php echo e(__('Clear System Cache')); ?>">
            <i data-lucide="sparkles" class="w-4 h-4"></i>
        </a>

        <div class="relative">
            <button id="lang-dropdown-toggle" class="topbar-dropdown">
                <span><?php echo e(strtoupper($activeLocale)); ?></span>
                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
            </button>
            <div id="lang-dropdown-menu" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-50 border-[var(--card-border)]">
                <?php $__currentLoopData = \App\Models\Admin\Language::where('is_active', true)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lang): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <form method="POST" action="<?php echo e(route('admin.switch-language')); ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="locale" value="<?php echo e($lang->code); ?>">
                        <button type="submit" class="w-full text-start px-4 py-2 text-sm hover:bg-gray-50 <?php echo e($activeLocale == $lang->code ? 'font-bold text-[var(--primary)]' : 'text-[var(--text-primary)]'); ?>">
                            <?php echo e($lang->name); ?> (<?php echo e(strtoupper($lang->code)); ?>)
                        </button>
                    </form>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <div class="relative">
            <button id="curr-dropdown-toggle" class="topbar-dropdown">
                <span><?php echo e($activeCurrModel ? $activeCurrModel->symbol : '$'); ?></span> <?php echo e(strtoupper($activeCurrency)); ?>

                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
            </button>
            <div id="curr-dropdown-menu" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-50 border-[var(--card-border)]">
                <?php $__currentLoopData = \App\Models\Admin\Currency::where('is_active', true)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $curr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <form method="POST" action="<?php echo e(route('admin.switch-currency')); ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="currency" value="<?php echo e($curr->code); ?>">
                        <button type="submit" class="w-full text-start px-4 py-2 text-sm hover:bg-gray-50 <?php echo e($activeCurrency == $curr->code ? 'font-bold text-[var(--primary)]' : 'text-[var(--text-primary)]'); ?>">
                            <?php echo e($curr->name); ?> (<?php echo e($curr->code); ?>)
                        </button>
                    </form>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <div class="relative">
            <button id="user-dropdown-toggle" class="user-avatar">
                <?php echo e(strtoupper(substr(auth()->user()->name ?? 'U', 0, 1))); ?>

            </button>

            <div id="user-dropdown-menu" class="hidden absolute end-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-1 z-50 border-[var(--card-border)]">
                <a href="<?php echo e(route('admin.profile')); ?>" class="block px-4 py-2 text-sm hover:bg-gray-50 text-[var(--text-primary)]"><?php echo e(__('My Profile')); ?></a>
                <a href="<?php echo e(route('admin.settings.index')); ?>" class="block px-4 py-2 text-sm hover:bg-gray-50 text-[var(--text-primary)]"><?php echo e(__('Settings')); ?></a>
                <div class="bg-[var(--card-border)] h-[1px]"></div>
                <form method="POST" action="<?php echo e(route('logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" style="cursor: pointer;" class="w-full text-start px-4 py-2 text-sm hover:bg-gray-50 text-[var(--danger)]"><?php echo e(__('Logout')); ?></button>
                </form>
            </div>
        </div>
    </div>
</header>
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/components/topbar.blade.php ENDPATH**/ ?>