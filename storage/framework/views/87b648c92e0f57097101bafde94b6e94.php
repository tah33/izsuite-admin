<?php ($currentLangInfo = \App\Models\Admin\Language::where('code', app()->getLocale())->first()); ?>
<?php ($htmlDir = $currentLangInfo?->direction === 'rtl' ? 'rtl' : 'ltr'); ?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e($htmlDir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', __('Admin')); ?> - <?php echo e(setting('site_name', config('brand.name'))); ?></title>
    <?php echo $__env->yieldPushContent('meta'); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>

    <?php if (isset($component)) { $__componentOriginale9fa9ed266dc715d53c8af97d38fc27f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale9fa9ed266dc715d53c8af97d38fc27f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-head','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-head'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale9fa9ed266dc715d53c8af97d38fc27f)): ?>
<?php $attributes = $__attributesOriginale9fa9ed266dc715d53c8af97d38fc27f; ?>
<?php unset($__attributesOriginale9fa9ed266dc715d53c8af97d38fc27f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale9fa9ed266dc715d53c8af97d38fc27f)): ?>
<?php $component = $__componentOriginale9fa9ed266dc715d53c8af97d38fc27f; ?>
<?php unset($__componentOriginale9fa9ed266dc715d53c8af97d38fc27f); ?>
<?php endif; ?>

    <?php echo $__env->yieldPushContent('head'); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="min-h-screen bg-[var(--content-bg)]">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>

    <div class="flex min-h-screen">
        <?php if (! empty(trim($__env->yieldContent('sidebar')))): ?>
            <?php echo $__env->yieldContent('sidebar'); ?>
        <?php else: ?>
            <?php echo $__env->make('components.admin-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>

        <div class="flex-1 flex flex-col main-content-layout">
            <?php if (! empty(trim($__env->yieldContent('topbar')))): ?>
                <?php echo $__env->yieldContent('topbar'); ?>
            <?php else: ?>
                <?php echo $__env->make('components.topbar', ['panelTitle' => 'Admin Panel'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?>

            <main class="flex-1 p-6">
                <?php if(session('success')): ?>
                    <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--success-light)] text-[var(--primary-dark)]">
                        <?php echo e(session('success')); ?>

                    </div>
                <?php endif; ?>
                <?php if(session('error')): ?>
                    <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
                        <?php echo e(session('error')); ?>

                    </div>
                <?php endif; ?>

                <?php echo $__env->yieldContent('breadcrumbs'); ?>
                <?php echo $__env->yieldContent('content'); ?>
            </main>

            <footer class="px-6 py-4 text-center text-xs text-[var(--text-muted)] border-t border-[var(--card-border)]">
                &copy; <?php echo e(date('Y')); ?> <?php echo e(setting('site_name', config('brand.name'))); ?> - Admin Panel
            </footer>
        </div>
    </div>

    <?php echo $__env->yieldPushContent('scripts'); ?>

    <script>
        lucide.createIcons();

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if ((form.method || 'get').toLowerCase() !== 'get') {
                return;
            }

            if (form.querySelector('input[name="per_page"], select[name="per_page"]')) {
                return;
            }

            var currentUrl = new URL(window.location.href);
            var perPage = currentUrl.searchParams.get('per_page');

            if (!perPage) {
                return;
            }

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'per_page';
            hidden.value = perPage;
            form.appendChild(hidden);
        }, true);

        function openConfirmModal(id) {
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('open');
                lucide.createIcons();
                document.body.style.overflow = 'hidden';
            }
        }

        function closeConfirmModal(id, event) {
            if (event && event.target !== event.currentTarget) return;
            var modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.confirm-modal-overlay.open').forEach(function(m) {
                    m.classList.remove('open');
                    document.body.style.overflow = '';
                });
            }
        });
    </script>
</body>
</html>
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/layouts/admin.blade.php ENDPATH**/ ?>