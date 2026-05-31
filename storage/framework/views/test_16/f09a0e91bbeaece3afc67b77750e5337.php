    <div <?php echo e($attributes->class(["bg-base-100 border-base-content/10 border-b-[length:var(--border)]", "sticky top-0 z-10" => $sticky])); ?>>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(["flex items-center px-6 py-3",  "max-w-screen-2xl mx-auto" => !$fullWidth]); ?>">
            <div <?php echo e($brand?->attributes->class(["flex-1 flex items-center"])); ?>>
                <?php echo e($brand); ?>

            </div>
            <div <?php echo e($actions?->attributes->class(["flex items-center gap-4"])); ?>>
                <?php echo e($actions); ?>

            </div>
        </div>
    </div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_16/9bff5d2b7fad994a256349b4988662c6.blade.php ENDPATH**/ ?>