<?php $__env->startSection('title', 'Sign In'); ?>

<?php $__env->startSection('content'); ?>
    <h2 class="text-xl font-bold mb-1 text-[var(--text-primary)]">Welcome back</h2>
    <p class="text-sm mb-6 text-[var(--text-secondary)]">Sign in to access your dashboard</p>

    <?php if($errors->any()): ?>
        <div class="mb-4 p-3 rounded-lg text-sm bg-[var(--danger-light)]">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p><?php echo e($error); ?></p>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('login')); ?>">
        <?php echo csrf_field(); ?>

        <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" class="form-input" placeholder="you@example.com" required autofocus>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-input" placeholder="********" required>
        </div>

        <div class="flex items-center justify-between mb-6">
            <label class="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
                <input type="checkbox" name="remember" class="rounded text-[var(--primary)] accent-[var(--primary)]">
                Remember me
            </label>
        </div>

        <button type="submit" class="btn btn-primary w-full">
            Sign In
        </button>
    </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\izsuite-admin\resources\views/auth/login.blade.php ENDPATH**/ ?>