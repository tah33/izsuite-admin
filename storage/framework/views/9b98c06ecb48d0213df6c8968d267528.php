<?php $__env->startSection('title', __('Apps')); ?>

<?php $__env->startSection('content'); ?>
    <div class="mb-6 flex justify-end">
        <a href="<?php echo e(route('admin.apps.create')); ?>" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> <?php echo e(__('Add App')); ?>

        </a>
    </div>

    <form action="<?php echo e(route('admin.apps.index')); ?>" method="GET" class="card mb-3">
        <div class="flex flex-col md:flex-row md:items-end gap-3 w-full">
            <div class="search-input-wrapper flex-1 min-w-0 ![max-width:none]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="<?php echo e(__('Search apps...')); ?>" class="form-input search-input w-full">
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
                <a href="<?php echo e(route('admin.apps.index')); ?>" class="btn btn-secondary">
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
                        <th><?php echo e(__('App')); ?></th>
                        <th><?php echo e(__('Category')); ?></th>
                        <th><?php echo e(__('Price')); ?></th>
                        <th><?php echo e(__('Status')); ?></th>
                        <th><?php echo e(__('Active')); ?></th>
                        <th><?php echo e(__('Created')); ?></th>
                        <th class="text-end"><?php echo e(__('Actions')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $apps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]"><?php echo e($apps->firstItem() + $loop->index); ?></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <?php if($app->logo_url): ?>
                                        <img src="<?php echo e(asset('storage/'.$app->logo_url)); ?>" alt="<?php echo e($app->name); ?>" class="w-9 h-9 rounded-lg object-cover shrink-0">
                                    <?php else: ?>
                                        <div class="user-avatar w-9 h-9">
                                            <?php echo e(strtoupper(substr($app->name, 0, 1))); ?>

                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="font-semibold text-[var(--text-primary)]"><?php echo e($app->name); ?></span>
                                        <?php if($app->description): ?>
                                            <span class="block text-xs text-[var(--text-muted)] line-clamp-1"><?php echo e(\Illuminate\Support\Str::limit($app->description, 50)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($app->category): ?>
                                    <span class="badge badge-info"><?php echo e($app->category); ?></span>
                                <?php else: ?>
                                    <span class="text-[var(--text-muted)]">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-sm font-medium"><?php echo e(number_format((float) $app->price, 2)); ?></span>
                            </td>
                            <td>
                                <?php if($app->status === 'Recommended'): ?>
                                    <span class="badge badge-success"><?php echo e(__('Recommended')); ?></span>
                                <?php elseif($app->status === 'pending'): ?>
                                    <span class="badge badge-warning"><?php echo e(__('Pending')); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><?php echo e(__('Upcoming')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($app->is_active): ?>
                                    <span class="badge badge-success"><?php echo e(__('Yes')); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-inactive"><?php echo e(__('No')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">
                                <?php echo e($app->created_at->format('M d, Y')); ?>

                            </td>
                            <td class="text-end">
                                <div class="action-dropdown-wrapper relative inline-block">
                                    <button class="action-dropdown-trigger" onclick="toggleDropdown(this)">
                                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                    </button>
                                    <div class="action-dropdown">
                                        <a href="<?php echo e(route('admin.apps.edit', $app->id)); ?>" class="action-dropdown-item">
                                            <i data-lucide="edit-2" class="w-4 h-4"></i> <?php echo e(__('Edit')); ?>

                                        </a>
                                        <button type="button" class="action-dropdown-item w-full" onclick="openConfirmModal('delete-app-<?php echo e($app->id); ?>')">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i> <?php echo e(__('Delete')); ?>

                                        </button>
                                    </div>
                                </div>

                                <?php if (isset($component)) { $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-modal','data' => ['id' => 'delete-app-'.e($app->id).'','title' => __('Delete App?'),'message' => __('Are you sure you want to delete') . ' &quot;' . $app->name . '&quot;? ' . __('This action cannot be undone.'),'action' => route('admin.apps.destroy', $app->id),'method' => 'DELETE','confirmText' => __('Delete'),'confirmClass' => 'btn-danger','icon' => 'trash-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'delete-app-'.e($app->id).'','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete App?')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Are you sure you want to delete') . ' &quot;' . $app->name . '&quot;? ' . __('This action cannot be undone.')),'action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.apps.destroy', $app->id)),'method' => 'DELETE','confirm-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete')),'confirm-class' => 'btn-danger','icon' => 'trash-2']); ?>
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
                            <td colspan="8" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="app-window" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p><?php echo e(__('No apps found.')); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($apps->hasPages()): ?>
            <div class="p-4 border-t border-[var(--card-border)]">
                <?php echo e($apps->links()); ?>

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

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\izsuite-admin\resources\views/admin/apps/index.blade.php ENDPATH**/ ?>