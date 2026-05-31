    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = ''.e($uuid).''; ?>wire:key="<?php echo e($uuid); ?>">
        <div
            <?php echo e($attributes->class([
                    "flex justify-start items-center gap-4 px-3 py-3",
                    "hover:bg-base-200" => !$noHover,
                    "cursor-pointer" => $link
                ])); ?>

        >

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link && (data_get($item, $avatar) || !is_string($avatar))): ?>
                <div>
                    <a href="<?php echo e($link); ?>" wire:navigate>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- AVATAR -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($item, $avatar) || $fallbackAvatar && is_string($avatar)): ?>
                <div>
                    <div class="avatar">
                        <div class="w-11 rounded-full">
                            <img src="<?php echo e(data_get($item, $avatar)); ?>" <?php if($fallbackAvatar): ?> onerror="this.src='<?php echo e($fallbackAvatar); ?>'" <?php endif; ?> />
                        </div>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!is_string($avatar)): ?>
                <div <?php echo e($avatar->attributes->class([])); ?>>
                    <?php echo e($avatar); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link && (data_get($item, $avatar) || !is_string($avatar))): ?>
                    </a>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- CONTENT -->
            <div class="flex-1 overflow-hidden whitespace-nowrap text-ellipsis truncate mary-hideable">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link): ?>
                    <a href="<?php echo e($link); ?>" wire:navigate>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div>
                    <div <?php if(!is_string($value)): ?> <?php echo e($value->attributes->class(["font-semibold truncate"])); ?> <?php else: ?> class="font-semibold truncate" <?php endif; ?>>
                        <?php echo e(is_string($value) ? data_get($item, $value) : $value); ?>

                    </div>

                    <div <?php if(!is_string($subValue)): ?>  <?php echo e($subValue->attributes->class(["text-base-content/50 text-sm truncate"])); ?> <?php else: ?> class="text-base-content/50 text-sm truncate" <?php endif; ?>>
                        <?php echo e(is_string($subValue) ? data_get($item, $subValue) : $subValue); ?>

                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link): ?>
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- ACTION -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actions): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link && !Str::of($actions)->contains([':click', '@click' , 'href'])): ?>
                    <a href="<?php echo e($link); ?>" wire:navigate>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div <?php echo e($actions->attributes->class(["flex items-center gap-3 mary-hideable"])); ?>>
                            <?php echo e($actions); ?>

                    </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link && !Str::of($actions)->contains([':click', '@click' , 'href'])): ?>
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$noSeparator): ?>
            <hr class="border-t-[length:var(--border)] border-base-content/10"/>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_15/ea637a85c514d552998fdc79c2d38578.blade.php ENDPATH**/ ?>