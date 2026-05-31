<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label'     => '',
    'count'     => null,
    'color'     => 'blue',
    'open'      => true,
    'uppercase' => true,
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
    'label'     => '',
    'count'     => null,
    'color'     => 'blue',
    'open'      => true,
    'uppercase' => true,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $colors = [
        'blue'    => ['pill_bg' => 'bg-blue-50   dark:bg-blue-950/40',   'pill_border' => 'border-blue-200   dark:border-blue-800',   'pill_text' => 'text-blue-700   dark:text-blue-300',   'dot' => 'bg-blue-500',   'sep' => 'border-blue-100   dark:border-blue-900/50'],
        'amber'   => ['pill_bg' => 'bg-amber-50  dark:bg-amber-950/40',  'pill_border' => 'border-amber-200  dark:border-amber-800',  'pill_text' => 'text-amber-700  dark:text-amber-300',  'dot' => 'bg-amber-500',  'sep' => 'border-amber-100  dark:border-amber-900/50'],
        'rose'    => ['pill_bg' => 'bg-rose-50   dark:bg-rose-950/40',   'pill_border' => 'border-rose-200   dark:border-rose-800',   'pill_text' => 'text-rose-700   dark:text-rose-300',   'dot' => 'bg-rose-500',   'sep' => 'border-rose-100   dark:border-rose-900/50'],
        'violet'  => ['pill_bg' => 'bg-violet-50 dark:bg-violet-950/40', 'pill_border' => 'border-violet-200 dark:border-violet-800', 'pill_text' => 'text-violet-700 dark:text-violet-300', 'dot' => 'bg-violet-500', 'sep' => 'border-violet-100 dark:border-violet-900/50'],
        'emerald' => ['pill_bg' => 'bg-emerald-50 dark:bg-emerald-950/40','pill_border' => 'border-emerald-200 dark:border-emerald-800','pill_text' => 'text-emerald-700 dark:text-emerald-300','dot' => 'bg-emerald-500','sep' => 'border-emerald-100 dark:border-emerald-900/50'],
        'pink'    => ['pill_bg' => 'bg-pink-50   dark:bg-pink-950/40',   'pill_border' => 'border-pink-200   dark:border-pink-800',   'pill_text' => 'text-pink-700   dark:text-pink-300',   'dot' => 'bg-pink-500',   'sep' => 'border-pink-100   dark:border-pink-900/50'],
        'indigo'  => ['pill_bg' => 'bg-indigo-50 dark:bg-indigo-950/40', 'pill_border' => 'border-indigo-200 dark:border-indigo-800', 'pill_text' => 'text-indigo-700 dark:text-indigo-300', 'dot' => 'bg-indigo-500', 'sep' => 'border-indigo-100 dark:border-indigo-900/50'],
        'gray'    => ['pill_bg' => 'bg-base-200  dark:bg-base-300/20',   'pill_border' => 'border-base-300',                          'pill_text' => 'text-base-content/60',                  'dot' => 'bg-base-content/30','sep' => 'border-base-200'],
    ];
    $c = $colors[$color] ?? $colors['gray'];
?>

<section x-data="<?php echo e(json_encode(['open' => $open])); ?>" <?php echo e($attributes); ?>>

    <button type="button"
        class="mb-3 flex w-full items-center gap-3 text-left"
        @click="open = !open">

        <span class="inline-flex shrink-0 items-center gap-2 rounded-full <?php echo e($c['pill_bg']); ?> <?php echo e($c['pill_border']); ?> border px-4 py-1.5">
            <span class="h-2 w-2 rounded-full <?php echo e($c['dot']); ?>"></span>
            <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['text-sm font-bold', $c['pill_text'], 'uppercase tracking-wide' => $uppercase]); ?>"><?php echo e($label); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($count !== null): ?>
                <span class="text-xs <?php echo e($c['pill_text']); ?> opacity-60"><?php echo e($count); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($suffix)): ?><?php echo e($suffix); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </span>

        <div class="flex-1 border-t <?php echo e($c['sep']); ?>"></div>

        <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-chevron-down'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4 text-base-content/30 transition-transform duration-200',':class' => 'open ? \'\' : \'-rotate-90\'']); ?>
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

    </button>

    <div x-show="open" x-collapse>
        <?php echo e($slot); ?>

    </div>

</section>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/section-accordion.blade.php ENDPATH**/ ?>