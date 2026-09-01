


<div class="stat-card animate-fade-in-up <?php echo e($stagger ?? ''); ?>">
    <div>
        <p class="stat-label"><?php echo e($label); ?></p>
        <p class="stat-value"><?php echo e($value); ?></p>
        <?php if(!empty($trend)): ?>
            <p class="stat-trend <?php echo e($trendType ?? 'neutral'); ?>"><?php echo e($trend); ?></p>
        <?php endif; ?>
    </div>
    <div class="stat-icon <?php echo e($iconColor ?? 'green'); ?>">
        <i data-lucide="<?php echo e($icon ?? 'activity'); ?>" class="w-5 h-5"></i>
    </div>
</div>
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/components/stat-card.blade.php ENDPATH**/ ?>