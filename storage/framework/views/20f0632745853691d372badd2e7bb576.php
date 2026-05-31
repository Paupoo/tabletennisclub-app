<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['team']));

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

foreach (array_filter((['team']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="mb-12">
    <div class="bg-white rounded-lg shadow-xs border p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-club-blue"><?php echo e($team['name']); ?></h3>
            <div class="<?php echo e($team['position_class'] ?? 'bg-green-100 text-green-800'); ?> px-3 py-1 rounded-full text-sm font-medium text-center">
                <?php echo e($team['position']); ?>

            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold">Date</th>
                        <th class="text-left py-3 px-4 font-semibold">Adversaire</th>
                        <th class="text-left py-3 px-4 font-semibold hidden md:block"><?php echo e(__('Home/Away')); ?></th>
                        <th class="text-left py-3 px-4 font-semibold">Score</th>
                        <th class="text-left py-3 px-4 font-semibold hidden md:block"><?php echo e(__('Result')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $team['matches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4 hidden md:block"><?php echo e($match['date']); ?></td>
                            <td class="py-3 px-4 block md:hidden">13-12-24</td>
                            <td class="py-3 px-4"><?php echo e($match['opponent']); ?></td>
                            <td class="py-3 px-4 hidden md:block"><?php echo e($match['venue']); ?></td>
                            <td class="py-3 px-4 font-mono "><?php echo e($match['score']); ?></td>
                            <td class="py-3 px-4 hidden md:block">
                                <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                    'px-2 py-1 rounded-sm text-sm font-medium',
                                    'bg-green-100 text-green-800' => in_array($match['result'], ['Victoire', 'Forfait Adverse']),
                                    'bg-red-100 text-red-800'    => in_array($match['result'], ['Défaite', 'Forfait']),
                                    'bg-orange-100 text-orange-700' => in_array($match['result'], ['Forfait Général', 'Forfait Général Adverse']),
                                    'bg-gray-100 text-gray-800'  => ! in_array($match['result'], ['Victoire', 'Forfait Adverse', 'Défaite', 'Forfait', 'Forfait Général', 'Forfait Général Adverse']),
                                ]); ?>">
                                    <?php echo e($match['result']); ?>

                                </span>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-club-blue"><?php echo e($team['stats']['played']); ?></div>
                <div class="text-sm text-gray-600"><?php echo e(__('Matches Played')); ?></div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?php echo e($team['stats']['wins']); ?></div>
                <div class="text-sm text-gray-600">Victoires</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-red-600"><?php echo e($team['stats']['losses']); ?></div>
                <div class="text-sm text-gray-600"><?php echo e(__('Losses')); ?></div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-club-blue"><?php echo e($team['stats']['win_rate']); ?>%</div>
                <div class="text-sm text-gray-600">Taux de Victoire</div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/team-results.blade.php ENDPATH**/ ?>