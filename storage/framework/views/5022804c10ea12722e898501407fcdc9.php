<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['schedules' => []]));

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

foreach (array_filter((['schedules' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $activitiesByDay = collect($schedules)->groupBy('day');
?>

<div class="bg-white rounded-2xl shadow-sm border overflow-hidden" x-data="{ showDetails: false }">

    
    <div class="bg-gradient-to-r from-club-blue to-club-blue-light text-white px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Planning hebdomadaire</h3>
                <p class="text-sm opacity-75 mt-0.5">
                    <?php $count = collect($schedules)->count(); $days = $activitiesByDay->count(); ?>
                    <?php echo e($count); ?> activité<?php echo e($count > 1 ? 's' : ''); ?> sur <?php echo e($days); ?> jour<?php echo e($days > 1 ? 's' : ''); ?>

                </p>
            </div>

            
            <div class="inline-flex bg-white/15 rounded-lg p-1 gap-0.5 shrink-0">
                <button @click="showDetails = false"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150 cursor-pointer"
                        :class="showDetails ? 'text-white/60 hover:text-white' : 'bg-white/25 text-white shadow-sm'">
                    Aperçu
                </button>
                <button @click="showDetails = true"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150 cursor-pointer"
                        :class="!showDetails ? 'text-white/60 hover:text-white' : 'bg-white/25 text-white shadow-sm'">
                    Détails
                </button>
            </div>
        </div>
    </div>

    
    <div x-show="!showDetails" x-transition>
        <div class="grid grid-cols-7 divide-x divide-gray-100 border-b border-gray-100">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $daysOfWeek; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $dayActivities = $activitiesByDay->get($day, collect());
                    $isToday = $day === now()->locale('fr')->dayName;
                ?>
                <div class="min-h-28 p-3 <?php echo e($isToday ? 'bg-club-yellow/5' : 'hover:bg-gray-50'); ?> transition-colors">
                    <div class="text-center mb-3">
                        <span class="text-xs font-semibold <?php echo e($isToday ? 'text-club-blue' : 'text-gray-500'); ?>">
                            <?php echo e(mb_strtoupper(mb_substr($day, 0, 3))); ?>

                        </span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isToday): ?>
                            <div class="w-1 h-1 bg-club-yellow rounded-full mx-auto mt-1"></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="space-y-1.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $dayActivities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                [$dotColor, $pillBg, $pillText] = match ($activity['type'] ?? null) {
                                    'Directed'   => ['bg-blue-500',  'bg-blue-50',  'text-blue-800'],
                                    'Free'       => ['bg-gray-300',  'bg-gray-50',  'text-gray-500'],
                                    'Supervised' => ['bg-amber-400', 'bg-amber-50', 'text-amber-800'],
                                    'match'      => ['bg-red-400',   'bg-red-50',   'text-red-700'],
                                    default      => ['bg-gray-300',  'bg-gray-50',  'text-gray-500'],
                                };
                            ?>
                            <div class="rounded px-1.5 py-1 <?php echo e($pillBg); ?> <?php echo e($pillText); ?>">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <div class="w-1.5 h-1.5 rounded-full <?php echo e($dotColor); ?> shrink-0"></div>
                                    <span class="text-xs font-semibold">
                                        <?php echo e(explode(' – ', $activity['time'])[0]); ?>

                                    </span>
                                </div>
                                <div class="text-xs opacity-70 leading-tight truncate pl-2.5">
                                    <?php echo e($activity['activity']); ?>

                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="text-gray-300 text-xs text-center py-3">—</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <div class="px-6 py-3 flex items-center gap-4 text-xs text-gray-400 flex-wrap">
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div><?php echo e(__('Directed')); ?></div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-400"></div><?php echo e(__('Supervised')); ?></div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-gray-300"></div> Libre</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-400"></div> Interclubs</div>
        </div>
    </div>

    
    <div x-show="showDetails" x-transition>
        <div class="grid grid-cols-7 divide-x divide-gray-100 border-b border-gray-100">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $daysOfWeek; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $dayActivities = $activitiesByDay->get($day, collect());
                    $isToday = $day === now()->locale('fr')->dayName;
                ?>
                <div class="min-h-32 p-3 <?php echo e($isToday ? 'bg-club-yellow/5' : ''); ?>">
                    <div class="text-center mb-3">
                        <span class="text-xs font-semibold <?php echo e($isToday ? 'text-club-blue' : 'text-gray-600'); ?>">
                            <?php echo e($day); ?>

                        </span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isToday): ?>
                            <div class="w-1 h-1 bg-club-yellow rounded-full mx-auto mt-1"></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="space-y-1.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $dayActivities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                [$cellBorder, $cellBg, $cellText] = match ($activity['type'] ?? null) {
                                    'Directed'   => ['border-blue-400',  'bg-blue-50',   'text-blue-800'],
                                    'Free'       => ['border-gray-300',  'bg-gray-50',   'text-gray-600'],
                                    'Supervised' => ['border-amber-400', 'bg-amber-50',  'text-amber-800'],
                                    'match'      => ['border-red-400',   'bg-red-50',    'text-red-700'],
                                    default      => ['border-gray-200',  'bg-gray-50',   'text-gray-600'],
                                };
                                $levelClass = match ($activity['level'] ?? null) {
                                    'Tous niveaux'   => 'bg-blue-100 text-blue-700',
                                    'Débutant'       => 'bg-green-100 text-green-700',
                                    'Jeunes'         => 'bg-green-100 text-green-700',
                                    'Confirmé'       => 'bg-orange-100 text-orange-700',
                                    'Compétition'    => 'bg-red-100 text-red-700',
                                    'Jeunes espoirs' => 'bg-purple-100 text-purple-700',
                                    default          => null,
                                };
                            ?>
                            <div class="<?php echo e($cellBg); ?> <?php echo e($cellText); ?> text-xs p-2 rounded border-l-2 <?php echo e($cellBorder); ?>">
                                <div class="font-semibold leading-tight"><?php echo e($activity['activity']); ?></div>
                                <div class="opacity-70 mt-0.5"><?php echo e($activity['time']); ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($activity['coach'])): ?>
                                    <div class="opacity-60 mt-0.5 truncate"><?php echo e($activity['coach']); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($levelClass): ?>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs mt-1 <?php echo e($levelClass); ?>">
                                        <?php echo e($activity['level']); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activity['is_open_enrollment'] ?? false): ?>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs mt-1 bg-gray-100 text-gray-500">
                                        Entrée libre
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="text-gray-300 text-xs text-center py-3">—</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/schedule-calendar-view.blade.php ENDPATH**/ ?>