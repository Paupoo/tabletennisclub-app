    <details
        x-data="{open: false}"
        @click.outside="open = false"
        :open="open"
        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
            'overflow-visible',
            'dropdown',
            'dropdown-end' => ($noXAnchor && $right),
            'dropdown-top' => ($noXAnchor && $top),
            'dropdown-bottom' => $noXAnchor,
        ]); ?>"
    >
        <!-- CUSTOM TRIGGER -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trigger): ?>
            <summary x-ref="button" @click.prevent="open = !open" <?php echo e($trigger->attributes->class(['list-none'])); ?>>
                <?php echo e($trigger); ?>

            </summary>
        <?php else: ?>
            <!-- DEFAULT TRIGGER -->
            <summary x-ref="button" @click.prevent="open = !open" <?php echo e($attributes->class(["btn"])); ?>>
                <?php echo e($label); ?>

                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => $icon] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mary-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce0070e6ae017cca68172d0230e44821)): ?>
<?php $attributes = $__attributesOriginalce0070e6ae017cca68172d0230e44821; ?>
<?php unset($__attributesOriginalce0070e6ae017cca68172d0230e44821); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce0070e6ae017cca68172d0230e44821)): ?>
<?php $component = $__componentOriginalce0070e6ae017cca68172d0230e44821; ?>
<?php unset($__componentOriginalce0070e6ae017cca68172d0230e44821); ?>
<?php endif; ?>
            </summary>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <ul
            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                'p-2','shadow','menu','z-[1]','border-[length:var(--border)]','border-base-content/10','bg-base-100', 'rounded-box','w-auto','min-w-max',
                'dropdown-content' => $noXAnchor,
                $maxHeight => $scroll,
                'overflow-y-auto' => $scroll,
            ]); ?>"
            @click="open = false"
            <?php if(!$noXAnchor): ?>
                x-anchor.<?php echo e($right ? 'bottom-end' : 'bottom-start'); ?>="$refs.button"
            <?php endif; ?>
        >
            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dropdown-slot-'.e($uuid).''; ?>wire:key="dropdown-slot-<?php echo e($uuid); ?>">
                <?php echo e($slot); ?>

            </div>
        </ul>
    </details><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_11/5db74ab7a4f6048446424f5d1781885f.blade.php ENDPATH**/ ?>