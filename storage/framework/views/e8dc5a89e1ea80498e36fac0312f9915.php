

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'id',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'action' => '#',
    'method' => 'POST',
    'confirmText' => 'Confirm',
    'confirmClass' => 'btn-danger',
    'cancelText' => 'Cancel',
    'icon' => 'alert-triangle',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'id',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'action' => '#',
    'method' => 'POST',
    'confirmText' => 'Confirm',
    'confirmClass' => 'btn-danger',
    'cancelText' => 'Cancel',
    'icon' => 'alert-triangle',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div id="<?php echo e($id); ?>" class="confirm-modal-overlay" onclick="closeConfirmModal('<?php echo e($id); ?>', event)">
    <div class="confirm-modal" onclick="event.stopPropagation()">
        
        <div class="confirm-modal-icon">
            <i data-lucide="<?php echo e($icon); ?>" class="w-6 h-6"></i>
        </div>

        
        <h3 class="confirm-modal-title"><?php echo e($title); ?></h3>
        <p class="confirm-modal-message"><?php echo e($message); ?></p>

        
        <div class="confirm-modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeConfirmModal('<?php echo e($id); ?>')">
                <?php echo e($cancelText); ?>

            </button>
            <form action="<?php echo e($action); ?>" method="POST" class="inline">
                <?php echo csrf_field(); ?>
                <?php echo method_field($method); ?>
                <button type="submit" class="btn <?php echo e($confirmClass); ?>">
                    <?php echo e($confirmText); ?>

                </button>
            </form>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\izsuite-admin\resources\views/components/confirm-modal.blade.php ENDPATH**/ ?>