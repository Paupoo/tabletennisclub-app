<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['event']));

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

foreach (array_filter((['event']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-show="selectedCategory === 'all' || selectedCategory === '<?php echo e($event['category']); ?>'" 
     x-transition
     class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-club-blue transition-colors">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="<?php if($event['category'] === 'tournament'): ?> bg-club-blue text-white <?php elseif($event['category'] === 'training'): ?> bg-gray-800 text-white <?php else: ?> bg-club-yellow text-club-blue <?php endif; ?> text-xs font-medium px-3 py-1 rounded-full uppercase">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event['category'] === 'tournament'): ?>
                    Tournoi
                <?php elseif($event['category'] === 'training'): ?>
                    Entraînement
                <?php elseif($event['category'] === 'club-life'): ?>
                    Vie du club
                <?php else: ?>
                    Social
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </span>
            <span class="text-2xl"><?php echo e($event['icon']); ?></span>
        </div>
        <h3 class="text-xl font-bold mb-2 text-gray-900"><?php echo e($event['title']); ?></h3>
        <p class="text-gray-600 mb-4"><?php echo e($event['description']); ?></p>
        
        <div class="space-y-2 mb-6">
            <div class="flex items-center text-sm text-gray-600">
                <span class="mr-3 w-4">📅</span>
                <span><?php echo e($event['date']); ?></span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($event['time']) && $event['time'] !== '00:00'): ?>
                <div class="flex items-center text-sm text-gray-600">
                    <span class="mr-3 w-4">⏰</span>
                    <span><?php echo e($event['time']); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($event['location'])): ?>
                <div class="flex items-center text-sm text-gray-600">
                    <span class="mr-3 w-4">📍</span>
                    <span><?php echo e($event['location']); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($event['price'])): ?>
                <div class="flex items-center text-sm text-gray-600">
                    <span class="mr-3 w-4">🎟️</span>
                    <span><?php echo e($event['price']); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        
    </div>
</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/event-card.blade.php ENDPATH**/ ?>