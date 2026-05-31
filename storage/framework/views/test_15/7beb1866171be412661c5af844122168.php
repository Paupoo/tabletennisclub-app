    <?php foreach ((['noJoin' => null]) as $__key => $__value) {
    $__consumeVariable = is_string($__key) ? $__key : $__value;
    $$__consumeVariable = is_string($__key) ? $__env->getConsumableComponentData($__key, $__value) : $__env->getConsumableComponentData($__value);
} ?>

    <div
        <?php echo e($attributes->class([
                'collapse border-[length:var(--border)] border-base-content/10',
                'join-item' => !$noJoin,
                'collapse-arrow' => !$collapsePlusMinus && !$noIcon,
                'collapse-plus' => $collapsePlusMinus && !$noIcon,
            ])->except(['open'])); ?>


        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'collapse-'.e($uuid).''; ?>wire:key="collapse-<?php echo e($uuid); ?>"
    >
            <!-- Detects if it is inside an accordion.  -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($noJoin)): ?>
                <input id="radio-<?php echo e($uuid); ?>" type="radio" value="<?php echo e($name); ?>" x-model="model" />
            <?php else: ?>
                <input id="checkbox-<?php echo e($uuid); ?>" <?php echo e($attributes->wire('model')); ?> <?php if($open): ?> checked <?php endif; ?> type="checkbox" />
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div
                <?php echo e($heading->attributes->merge(["class" => "collapse-title font-semibold"])); ?>


                <?php if(isset($noJoin)): ?>
                    :class="model == '<?php echo e($name); ?>' && 'z-10'"
                    @click="if (model == '<?php echo e($name); ?>') model = null"
                <?php endif; ?>
            >
                <?php echo e($heading); ?>

            </div>
            <div <?php echo e($content->attributes->merge(["class" => "collapse-content text-sm"])); ?> <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'content-'.e($uuid).''; ?>wire:key="content-<?php echo e($uuid); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($separator): ?>
                    <hr class="mb-3 border-t-[length:var(--border)] border-base-content/10" />

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressIndicator): ?>
                        <div class="h-0.5 -mt-6.5 mb-6.5">
                            <progress
                                class="progress progress-primary w-full h-0.5"
                                wire:loading

                                <?php if($progressTarget()): ?>
                                    wire:target="<?php echo e($progressTarget()); ?>"
                                 <?php endif; ?>></progress>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php echo e($content); ?>

            </div>
    </div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_15/f474cb129281b0c451565cd6c8c80349.blade.php ENDPATH**/ ?>