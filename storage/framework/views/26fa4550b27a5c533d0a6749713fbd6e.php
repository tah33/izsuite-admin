<?php $__env->startSection('title', __('App Categories')); ?>

<?php $__env->startSection('content'); ?>
    <div class="mb-6 flex justify-end">
        <a href="<?php echo e(route('admin.app-categories.create')); ?>" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> <?php echo e(__('Add Category')); ?>

        </a>
    </div>

    <form action="<?php echo e(route('admin.app-categories.index')); ?>" method="GET" class="card mb-3">
        <div class="flex flex-col md:flex-row md:items-end gap-3 w-full">
            <div class="search-input-wrapper flex-1 min-w-0 ![max-width:none]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="<?php echo e(__('Search categories...')); ?>" class="form-input search-input w-full">
            </div>
            <div class="md:w-[140px] shrink-0">
                <select name="per_page" class="form-select w-full" onchange="this.form.submit()">
                    <?php $__currentLoopData = [10, 15, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option); ?>" <?php echo e((int) request('per_page', 15) === $option ? 'selected' : ''); ?>><?php echo e($option); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4 me-1"></i><?php echo e(__('Filter')); ?>

                </button>
                <a href="<?php echo e(route('admin.app-categories.index')); ?>" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </form>

    
    <div class="card overflow-visible">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo e(__('Category Name')); ?></th>
                        <th><?php echo e(__('Active')); ?></th>
                        <th><?php echo e(__('Created')); ?></th>
                        <th class="text-end"><?php echo e(__('Actions')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]"><?php echo e($categories->firstItem() + $loop->index); ?></td>
                            <td>
                                <span class="font-semibold text-[var(--text-primary)]"><?php echo e($category->name); ?></span>
                            </td>
                            <td>
                                <form action="<?php echo e(route('admin.app-categories.toggle-status', $category->id)); ?>" method="POST" class="inline-block" onchange="this.request ? this.requestSubmit() : this.submit()">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" class="toggle-switch" <?php echo e($category->is_active ? 'checked' : ''); ?> onchange="this.form.submit()">
                                    </label>
                                </form>
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">
                                <?php echo e($category->created_at->format('M d, Y')); ?>

                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="<?php echo e(route('admin.app-categories.edit', $category->id)); ?>" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> <?php echo e(__('Edit')); ?>

                                        </a>
                                        <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-category-<?php echo e($category->id); ?>')">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i> <?php echo e(__('Delete')); ?>

                                        </button>
                                    </div>
                                </div>

                                <?php if (isset($component)) { $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-modal','data' => ['id' => 'delete-category-'.e($category->id).'','title' => __('Delete Category?'),'message' => __('Are you sure you want to delete') . ' &quot;' . $category->name . '&quot;? ' . __('This action cannot be undone.'),'action' => route('admin.app-categories.destroy', $category->id),'method' => 'DELETE','confirmText' => __('Delete'),'confirmClass' => 'btn-danger','icon' => 'trash-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'delete-category-'.e($category->id).'','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete Category?')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Are you sure you want to delete') . ' &quot;' . $category->name . '&quot;? ' . __('This action cannot be undone.')),'action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.app-categories.destroy', $category->id)),'method' => 'DELETE','confirm-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete')),'confirm-class' => 'btn-danger','icon' => 'trash-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10)): ?>
<?php $attributes = $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10; ?>
<?php unset($__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10)): ?>
<?php $component = $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10; ?>
<?php unset($__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10); ?>
<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="tags" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p><?php echo e(__('No app categories found.')); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($categories->hasPages()): ?>
            <div class="p-4 border-t border-[var(--card-border)]">
                <?php echo e($categories->links()); ?>

            </div>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function toggleDropdown(btn) {
        var dropdown = btn.nextElementSibling;
        document.querySelectorAll('.action-dropdown.show').forEach(function(dd) {
            if (dd !== dropdown) dd.classList.remove('show');
        });
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            var rect = btn.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = (rect.bottom + 4) + 'px';
            dropdown.style.right = (window.innerWidth - rect.right) + 'px';
            dropdown.style.left = 'auto';
            var ddRect = dropdown.getBoundingClientRect();
            if (ddRect.bottom > window.innerHeight) {
                dropdown.style.top = (rect.top - ddRect.height - 4) + 'px';
            }
        }
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.action-dropdown-wrapper')) {
            document.querySelectorAll('.action-dropdown.show').forEach(function(dd) {
                dd.classList.remove('show');
            });
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\izsuite-admin\resources\views/admin/app-categories/index.blade.php ENDPATH**/ ?>