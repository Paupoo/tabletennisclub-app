    <div
        <?php echo e($attributes
                ->merge(['wire:key' => $uuid ])
                ->class(['card bg-base-100 p-5', 'shadow-xs' => $shadow])); ?>

    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($figure): ?>
            <figure <?php echo e($figure->attributes->class(["mb-5 -m-5"])); ?>>
                <?php echo e($figure); ?>

            </figure>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title || $subtitle): ?>
            <div class="pb-5">
                <div class="flex gap-3 justify-between items-center w-full">
                    <div class="grow-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title): ?>
                            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(["text-xl font-bold", is_string($title) ? '' : $title?->attributes->get('class') ]); ?>" >
                                <?php echo e($title); ?>

                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subtitle): ?>
                        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(["text-base-content/50 text-sm mt-1", is_string($subtitle) ? '' : $subtitle?->attributes->get('class') ]); ?>" >
                                <?php echo e($subtitle); ?>

                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu): ?>
                        <div <?php echo e($menu->attributes->class(["flex items-center gap-2"])); ?>> <?php echo e($menu); ?> </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($separator): ?>
                    <hr class="mt-3 border-t-[length:var(--border)] border-base-content/10" />

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressIndicator): ?>
                        <div class="h-0.5 -mt-4 mb-4">
                            <progress
                                class="progress progress-primary w-full h-0.5"
                                wire:loading

                                <?php if($progressTarget()): ?>
                                    wire:target="<?php echo e($progressTarget()); ?>"
                                 <?php endif; ?>></progress>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([ 'grow-1', $bodyClass ]); ?>">
            <?php echo e($slot); ?>

        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actions): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($separator): ?>
                <hr class="mt-5 border-t-[length:var(--border)] border-base-content/10" />
            <?php else: ?>
                <div></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(["flex w-full items-end justify-end gap-3 pt-5", is_string($actions) ? '' : $actions?->attributes->get('class') ]); ?>">
                <?php echo e($actions); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_7/19854516fc7f6479f5615d5c57de845e.blade.php ENDPATH**/ ?>