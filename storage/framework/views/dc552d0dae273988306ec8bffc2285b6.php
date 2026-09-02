<aside id="sidebar" class="sidebar fixed top-0 start-0 flex flex-col z-40">
    <div class="sidebar-logo">
        <a href="<?php echo e(route('admin.overview')); ?>" class="flex items-center justify-center gap-2.5">
            <?php if(setting('site_logo')): ?>
                <?php if (isset($component)) { $__componentOriginal0e3e854f1972cb532cc8b5bc0ace80b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0e3e854f1972cb532cc8b5bc0ace80b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.site-logo','data' => ['class' => 'w-7 h-7']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('site-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-7 h-7']); ?>
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
                <span class="logo-text block leading-tight"><?php echo e(setting('site_name', config('brand.name'))); ?></span>
            <?php else: ?>
                
                <?php if (isset($component)) { $__componentOriginal0e3e854f1972cb532cc8b5bc0ace80b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0e3e854f1972cb532cc8b5bc0ace80b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.site-logo','data' => ['variant' => 'wordmark','tone' => 'light','class' => 'h-[22px] w-auto']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('site-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'wordmark','tone' => 'light','class' => 'h-[22px] w-auto']); ?>
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
            <?php endif; ?>
        </a>
    </div>

    <nav id="sidebar-nav" class="flex-1 py-4 overflow-y-auto">
        <a href="<?php echo e(route('admin.overview')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.overview') ? 'active' : ''); ?>">
            <i data-lucide="layout-dashboard" class="icon"></i>
            <span><?php echo e(__('Overview')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.staff.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.staff.*') ? 'active' : ''); ?>">
            <i data-lucide="user-check" class="icon"></i>
            <span><?php echo e(__('Staff')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.roles.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.roles.*') ? 'active' : ''); ?>">
            <i data-lucide="shield" class="icon"></i>
            <span><?php echo e(__('Roles')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.tickets.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.tickets.*') ? 'active' : ''); ?>">
            <i data-lucide="ticket" class="icon"></i>
            <span><?php echo e(__('Tickets')); ?></span>
        </a>

        <div class="sidebar-section-title"><?php echo e(__('Management')); ?></div>

        <a href="<?php echo e(route('admin.app-categories.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.app-categories.*') ? 'active' : ''); ?>">
            <i data-lucide="tags" class="icon"></i>
            <span><?php echo e(__('App Categories')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.apps.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.apps.*') ? 'active' : ''); ?>">
            <i data-lucide="app-window" class="icon"></i>
            <span><?php echo e(__('Apps')); ?></span>
        </a>


        <a href="<?php echo e(route('admin.departments.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.departments.*') ? 'active' : ''); ?>">
            <i data-lucide="layers" class="icon"></i>
            <span><?php echo e(__('Departments')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.plans.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.plans.*') ? 'active' : ''); ?>">
            <i data-lucide="tag" class="icon"></i>
            <span><?php echo e(__('Plans & Pricing')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.subscriptions.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.subscriptions.*') ? 'active' : ''); ?>">
            <i data-lucide="history" class="icon"></i>
            <span><?php echo e(__('Subscription History')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.payment-methods.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.payment-methods.*') ? 'active' : ''); ?>">
            <i data-lucide="wallet" class="icon"></i>
            <span><?php echo e(__('Payment Methods')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.faqs.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.faqs.*') ? 'active' : ''); ?>">
            <i data-lucide="circle-help" class="icon"></i>
            <span><?php echo e(__('FAQs')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.contact-messages.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.contact-messages.*') ? 'active' : ''); ?>">
            <i data-lucide="mail-plus" class="icon"></i>
            <span><?php echo e(__('Contact Messages')); ?></span>
        </a>

        <div class="sidebar-section-title"><?php echo e(__('Frontend CMS')); ?></div>

        <a href="<?php echo e(route('admin.content.index', ['context' => 'header-footer'])); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.content.*') ? 'active' : ''); ?>">
            <i data-lucide="panels-top-left" class="icon"></i>
            <span><?php echo e(__('Header & Footer')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.pages.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.pages.*') ? 'active' : ''); ?>">
            <i data-lucide="file-text" class="icon"></i>
            <span><?php echo e(__('Pages')); ?></span>
        </a>

        <div class="sidebar-section-title"><?php echo e(__('System')); ?></div>

        <a href="<?php echo e(route('admin.languages.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.languages.*') ? 'active' : ''); ?>">
            <i data-lucide="globe" class="icon"></i>
            <span><?php echo e(__('Languages')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.currencies.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.currencies.*') ? 'active' : ''); ?>">
            <i data-lucide="coins" class="icon"></i>
            <span><?php echo e(__('Currencies')); ?></span>
        </a>

        <a href="<?php echo e(route('admin.settings.index')); ?>" class="sidebar-link <?php echo e(request()->routeIs('admin.settings.*') ? 'active' : ''); ?>">
            <i data-lucide="settings" class="icon"></i>
            <span><?php echo e(__('Settings')); ?></span>
        </a>
    </nav>
</aside>
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/components/admin-sidebar.blade.php ENDPATH**/ ?>