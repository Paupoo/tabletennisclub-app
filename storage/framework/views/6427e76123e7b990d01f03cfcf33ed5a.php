    <a
        class="hidden tab"
        :class="{ 'tab-active': selected === '<?php echo e($name); ?>' }"
        data-name="<?php echo e($name); ?>"
        x-init="
                const newItem = { name: '<?php echo e($name); ?>', label: <?php echo e(json_encode($tabLabel($label))); ?>, disabled: <?php echo e($disabled ? 'true' : 'false'); ?>, hidden: <?php echo e($hidden ? 'true' : 'false'); ?> };
                const index = tabs.findIndex(item => item.name === '<?php echo e($name); ?>');
                index !== -1 ? tabs[index] = newItem : tabs.push(newItem);

                Livewire.hook('morph.removed', ({el}) => {
                    if (el.getAttribute('data-name') == '<?php echo e($name); ?>'){
                        tabs = tabs.filter(i => i.name !== '<?php echo e($name); ?>')
                    }
                })
            "
    ></a>

    <div x-show="selected === '<?php echo e($name); ?>'" role="tabpanel" <?php echo e($attributes->class("tab-content py-5 px-1")); ?>>
        <?php echo e($slot); ?>

    </div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/c17f5db9a07b7cd2b2f161309a6c418e.blade.php ENDPATH**/ ?>