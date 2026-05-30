<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['schedule', 'index' => 0]));

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

foreach (array_filter((['schedule', 'index' => 0]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $type = $schedule['type'] ?? null;

    [$borderClass, $typeBadgeClass, $typeLabel] = match ($type) {
        'Directed'   => ['border-l-4 border-blue-500',  'bg-blue-50 text-blue-700',   'Dirigé'],
        'Free'       => ['border-l-4 border-gray-300',  'bg-gray-100 text-gray-500',  'Libre'],
        'Supervised' => ['border-l-4 border-amber-400', 'bg-amber-50 text-amber-700', 'Supervisé'],
        'match'      => ['border-l-4 border-red-400',   'bg-red-50 text-red-600',     'Interclubs'],
        default      => ['border-l-4 border-gray-200',  'bg-gray-100 text-gray-500',  ''],
    };

    $levelBadgeClass = match ($schedule['level'] ?? null) {
        'Tous niveaux'   => 'bg-blue-100 text-blue-700',
        'Débutant'       => 'bg-green-100 text-green-700',
        'Jeunes'         => 'bg-green-100 text-green-700',
        'Confirmé'       => 'bg-orange-100 text-orange-700',
        'Compétition'    => 'bg-red-100 text-red-700',
        'Jeunes espoirs' => 'bg-purple-100 text-purple-700',
        default          => 'bg-gray-100 text-gray-600',
    };
?>

<div class="bg-white rounded-lg border border-gray-200 <?php echo e($borderClass); ?> hover:shadow-md transition-shadow duration-200 animate-on-scroll"
     style="animation-delay: <?php echo e($index * 0.05); ?>s">
    <div class="p-5">
        <div class="flex items-start justify-between gap-4">

            
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($typeLabel): ?>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo e($typeBadgeClass); ?>">
                            <?php echo e($typeLabel); ?>

                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="font-bold text-gray-900 text-base leading-tight"><?php echo e($schedule['activity']); ?></span>
                </div>

                <div class="flex items-center gap-x-3 gap-y-1 flex-wrap text-sm text-gray-500">
                    <span class="font-medium text-gray-700"><?php echo e($schedule['time']); ?></span>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($schedule['location'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <?php echo e($schedule['location']); ?>

                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($schedule['coach'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <?php echo e($schedule['coach']); ?>

                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($schedule['description'])): ?>
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed"><?php echo e($schedule['description']); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($schedule['level'])): ?>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?php echo e($levelBadgeClass); ?> shrink-0 self-start">
                    <?php echo e($schedule['level']); ?>

                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/schedule-card.blade.php ENDPATH**/ ?>