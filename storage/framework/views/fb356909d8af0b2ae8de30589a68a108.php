<?php $__env->startSection('title', __('Plans & Pricing')); ?>

<?php $__env->startSection('content'); ?>
    <div class="mb-6 flex justify-end">
        <a href="<?php echo e(route('admin.plans.create')); ?>" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <?php echo e(__('New Plan')); ?>

        </a>
    </div>

    
    <?php if(session('error')): ?>
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?>

    
    <div class="flex items-center gap-2 mb-4">
        <span class="text-sm font-medium text-[var(--text-primary)]"><?php echo e(__('Show prices:')); ?></span>
        <div class="inline-flex rounded-lg overflow-hidden border border-[var(--card-border)]" id="billing-toggle">
            <button type="button" class="billing-tab px-4 py-1.5 text-sm font-medium transition-all bg-[var(--primary)] text-white" data-interval="monthly"><?php echo e(__('Monthly')); ?></button>
            <button type="button" class="billing-tab px-4 py-1.5 text-sm font-medium transition-all text-[var(--text-muted)] hover:text-[var(--text-primary)] hover:bg-[var(--card-bg)]" data-interval="yearly"><?php echo e(__('Yearly')); ?></button>
        </div>
    </div>

    
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo e(__('Plan')); ?></th>
                        <th><?php echo e(__('Audience')); ?></th>
                        <th><?php echo e(__('Type')); ?></th>
                        <th class="price-col" data-interval="monthly"><?php echo e(__('Monthly Price')); ?></th>
                        <th class="price-col" data-interval="yearly" class="hidden"><?php echo e(__('Yearly Price')); ?></th>
                        <th><?php echo e(__('Trial')); ?></th>
                        <th><?php echo e(__('Users')); ?></th>
                        <th><?php echo e(__('Limits')); ?></th>
                        <th><?php echo e(__('Status')); ?></th>
                        <th class="text-end"><?php echo e(__('Actions')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="text-[var(--text-muted)]"><?php echo e($loop->iteration + ($plans->currentPage() - 1) * $plans->perPage()); ?></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-[var(--text-primary)]"><?php echo e($plan->name); ?></span>
                                    <?php if($plan->is_featured): ?>
                                        <span class="badge badge-warning text-xs"><?php echo e(__('Featured')); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if($plan->description): ?>
                                    <p class="text-xs mt-0.5 text-[var(--text-muted)]"><?php echo e(Str::limit($plan->description, 60)); ?></p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo e(ucfirst($plan->plan_for ?? 'recruiter')); ?></span>
                            </td>
                            <td>
                                <?php if(($plan->billing_type ?? 'monthly') === 'yearly'): ?>
                                    <span class="badge badge-warning"><?php echo e(__('Yearly')); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-info"><?php echo e(__('Monthly')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="price-col font-semibold text-[var(--primary)]" data-interval="monthly">
                                <?php echo e(format_price($plan->monthly_price)); ?>

                            </td>
                            <td class="price-col font-semibold text-[var(--primary)]" data-interval="yearly" class="hidden">
                                <?php echo e(format_price($plan->yearly_price)); ?>

                            </td>
                            <td>
                                <?php if($plan->trial_days > 0): ?>
                                    <span class="text-sm"><?php echo e($plan->trial_days); ?> <?php echo e(__('days')); ?></span>
                                <?php else: ?>
                                    <span class="text-[var(--text-muted)]">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo e($plan->subscriptions_count); ?></span>
                            </td>
                            <td class="text-xs text-[var(--text-muted)]">
                                <?php echo e(__('Jobs')); ?>: <?php echo e($plan->job_postings_limit ?? __('Unlimited')); ?><br>
                                <?php echo e(__('AI')); ?>: <?php echo e($plan->ai_screenings_limit ?? __('Unlimited')); ?><br>
                                <?php echo e(__('Team')); ?>: <?php echo e($plan->team_members_limit ?? __('Unlimited')); ?>

                            </td>
                            <td>
                                <?php if($plan->is_active): ?>
                                    <span class="badge badge-success"><?php echo e(__('Active')); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?php echo e(__('Inactive')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="<?php echo e(__('Delete')); ?>" onclick="openConfirmModal('delete-plan-<?php echo e($plan->id); ?>')">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <a href="<?php echo e(route('admin.plans.edit', $plan->id)); ?>" class="btn btn-xs btn-secondary" title="<?php echo e(__('Edit')); ?>">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>

                                    <?php if (isset($component)) { $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-modal','data' => ['id' => 'delete-plan-'.e($plan->id).'','title' => __('Delete Plan?'),'message' => __('Are you sure you want to delete this plan? This action cannot be undone.'),'action' => route('admin.plans.destroy', $plan->id),'method' => 'DELETE','confirmText' => __('Delete'),'confirmClass' => 'btn-danger','icon' => 'trash-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'delete-plan-'.e($plan->id).'','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete Plan?')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Are you sure you want to delete this plan? This action cannot be undone.')),'action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('admin.plans.destroy', $plan->id)),'method' => 'DELETE','confirm-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete')),'confirm-class' => 'btn-danger','icon' => 'trash-2']); ?>
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
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="11" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="tag" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p><?php echo e(__('No plans yet. Create your first plan.')); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($plans->hasPages()): ?>
            <div class="p-4 border-t border-[var(--card-border)]">
                <?php echo e($plans->links()); ?>

            </div>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.querySelectorAll('.billing-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            const interval = btn.dataset.interval;
            document.querySelectorAll('.billing-tab').forEach(b => {
                const isActive = (b === btn);
                b.classList.toggle('bg-[var(--primary)]', isActive);
                b.classList.toggle('text-white', isActive);
                
                b.classList.toggle('text-[var(--text-muted)]', !isActive);
                b.classList.toggle('hover:text-[var(--text-primary)]', !isActive);
                b.classList.toggle('hover:bg-[var(--card-bg)]', !isActive);
            });
            document.querySelectorAll('.price-col').forEach(col => {
                col.classList.toggle('hidden', col.dataset.interval !== interval);
            });
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\izsuite-admin\resources\views/admin/plans/index.blade.php ENDPATH**/ ?>