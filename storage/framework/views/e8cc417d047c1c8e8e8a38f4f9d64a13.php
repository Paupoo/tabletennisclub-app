<?php
    $colorMap = [
        'blue'    => ['border' => 'hover:border-blue-300   dark:hover:border-blue-700',   'icon_bg' => 'bg-blue-100   dark:bg-blue-900/40',   'icon_text' => 'text-blue-600   dark:text-blue-400'],
        'cyan'    => ['border' => 'hover:border-cyan-300   dark:hover:border-cyan-700',   'icon_bg' => 'bg-cyan-100   dark:bg-cyan-900/40',   'icon_text' => 'text-cyan-600   dark:text-cyan-400'],
        'teal'    => ['border' => 'hover:border-teal-300   dark:hover:border-teal-700',   'icon_bg' => 'bg-teal-100   dark:bg-teal-900/40',   'icon_text' => 'text-teal-600   dark:text-teal-400'],
        'indigo'  => ['border' => 'hover:border-indigo-300 dark:hover:border-indigo-700', 'icon_bg' => 'bg-indigo-100 dark:bg-indigo-900/40', 'icon_text' => 'text-indigo-600 dark:text-indigo-400'],
        'violet'  => ['border' => 'hover:border-violet-300 dark:hover:border-violet-700', 'icon_bg' => 'bg-violet-100 dark:bg-violet-900/40', 'icon_text' => 'text-violet-600 dark:text-violet-400'],
        'purple'  => ['border' => 'hover:border-purple-300 dark:hover:border-purple-700', 'icon_bg' => 'bg-purple-100 dark:bg-purple-900/40', 'icon_text' => 'text-purple-600 dark:text-purple-400'],
        'rose'    => ['border' => 'hover:border-rose-300   dark:hover:border-rose-700',   'icon_bg' => 'bg-rose-100   dark:bg-rose-900/40',   'icon_text' => 'text-rose-600   dark:text-rose-400'],
        'orange'  => ['border' => 'hover:border-orange-300 dark:hover:border-orange-700', 'icon_bg' => 'bg-orange-100 dark:bg-orange-900/40', 'icon_text' => 'text-orange-600 dark:text-orange-400'],
        'amber'   => ['border' => 'hover:border-amber-300  dark:hover:border-amber-700',  'icon_bg' => 'bg-amber-100  dark:bg-amber-900/40',  'icon_text' => 'text-amber-600  dark:text-amber-400'],
        'yellow'  => ['border' => 'hover:border-yellow-300 dark:hover:border-yellow-700', 'icon_bg' => 'bg-yellow-100 dark:bg-yellow-900/40', 'icon_text' => 'text-yellow-600 dark:text-yellow-400'],
        'emerald' => ['border' => 'hover:border-emerald-300 dark:hover:border-emerald-700','icon_bg' => 'bg-emerald-100 dark:bg-emerald-900/40','icon_text' => 'text-emerald-600 dark:text-emerald-400'],
        'pink'    => ['border' => 'hover:border-pink-300   dark:hover:border-pink-700',   'icon_bg' => 'bg-pink-100   dark:bg-pink-900/40',   'icon_text' => 'text-pink-600   dark:text-pink-400'],
        'slate'   => ['border' => 'hover:border-slate-300  dark:hover:border-slate-600',  'icon_bg' => 'bg-slate-100  dark:bg-slate-800/60',  'icon_text' => 'text-slate-600  dark:text-slate-400'],
        'gray'    => ['border' => 'hover:border-base-300',                                'icon_bg' => 'bg-base-200',                          'icon_text' => 'text-base-content/50'],
    ];
    $c = $colorMap[$color ?? 'gray'];
?>

<a href="#"
   class="group relative bg-base-100 rounded-xl border border-base-200 <?php echo e($c['border']); ?> hover:shadow-md transition-all p-4 flex flex-col items-center gap-2 text-center">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($badge) && $badge > 0): ?>
    <span class="absolute top-2 right-2">
        <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => ''.e($badge).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'badge-error badge-xs']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f015fb6508e425790bdb8f79792e6ed)): ?>
<?php $attributes = $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed; ?>
<?php unset($__attributesOriginal4f015fb6508e425790bdb8f79792e6ed); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f015fb6508e425790bdb8f79792e6ed)): ?>
<?php $component = $__componentOriginal4f015fb6508e425790bdb8f79792e6ed; ?>
<?php unset($__componentOriginal4f015fb6508e425790bdb8f79792e6ed); ?>
<?php endif; ?>
    </span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="<?php echo e($c['icon_bg']); ?> <?php echo e($c['icon_text']); ?> rounded-xl p-2.5 group-hover:scale-110 transition-transform">
        <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => ''.e($icon).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-5 h-5']); ?>
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
    </div>
    <span class="text-xs font-semibold text-base-content leading-tight"><?php echo e($label); ?></span>
    <span class="text-xs text-base-content/40 leading-tight"><?php echo e($sub); ?></span>
</a>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/clubAdmin/_dashboard_tile.blade.php ENDPATH**/ ?>