<div class="flex items-center gap-3">
    <div class="avatar <?php if(empty($image)): ?> avatar-placeholder <?php endif; ?>">
        <div <?php echo e($attributes->class(["w-7 rounded-full", "bg-neutral text-neutral-content" => empty($image)])); ?>>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($image)): ?>
                <span class="text-xs" alt="<?php echo e($alt); ?>"><?php echo e($placeholder); ?></span>
            <?php else: ?>
                <img src="<?php echo e($image); ?>" alt="<?php echo e($alt); ?>" <?php if($fallbackImage): ?> onerror="this.src='<?php echo e($fallbackImage); ?>'" <?php endif; ?> />
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title || $subtitle): ?>
    <div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title): ?>
            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(["font-semibold font-lg", is_string($title) ? '' : $title?->attributes->get('class') ]); ?>" >
                <?php echo e($title); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subtitle): ?>
            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(["text-sm text-base-content/50", is_string($subtitle) ? '' : $subtitle?->attributes->get('class') ]); ?>" >
                <?php echo e($subtitle); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_10/b0a514e55bf144b587a7a63786021385.blade.php ENDPATH**/ ?>