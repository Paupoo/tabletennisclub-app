<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['schedules' => [], 'compact' => false]));

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

foreach (array_filter((['schedules' => [], 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $activitiesByDay = collect($schedules)->groupBy('day');
?>

<div class="<?php echo e($compact ? 'p-4' : 'p-0'); ?> bg-white rounded-2xl shadow-sm border overflow-hidden" x-data="{ showDetails: false }">

    
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div>
            <h4 class="<?php echo e($compact ? 'text-sm font-semibold' : 'font-bold'); ?> text-gray-900">
                Planning hebdomadaire
            </h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$compact): ?>
                <p class="text-xs text-gray-400 mt-0.5">
                    <?php $count = collect($schedules)->count(); ?>
                    <?php echo e($count); ?> activité<?php echo e($count > 1 ? 's' : ''); ?> cette semaine
                </p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="inline-flex bg-gray-100 rounded-lg p-1 gap-0.5 shrink-0">
            <button @click="showDetails = false"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all duration-150 cursor-pointer"
                    :class="showDetails ? 'text-gray-400 hover:text-gray-600' : 'bg-white text-gray-900 shadow-sm'">
                Aperçu
            </button>
            <button @click="showDetails = true"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all duration-150 cursor-pointer"
                    :class="!showDetails ? 'text-gray-400 hover:text-gray-600' : 'bg-white text-gray-900 shadow-sm'">
                Détails
            </button>
        </div>
    </div>

    
    <div x-show="!showDetails" x-transition class="px-5 py-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activitiesByDay->isEmpty()): ?>
            <p class="text-sm text-gray-400 text-center py-2"><?php echo e(__('No scheduled activity.')); ?></p>
        <?php else: ?>
            <div class="space-y-3.5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $activitiesByDay; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day => $activities): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xs font-bold uppercase tracking-widest text-gray-400"><?php echo e($day); ?></span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div class="space-y-1.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $activities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $dotColor = match ($activity['type'] ?? null) {
                                        'Directed'   => 'bg-blue-500',
                                        'Free'       => 'bg-gray-300',
                                        'Supervised' => 'bg-amber-400',
                                        'match'      => 'bg-red-400',
                                        default      => 'bg-gray-300',
                                    };
                                ?>
                                <div class="flex items-center gap-2 pl-1">
                                    <div class="w-2 h-2 rounded-full <?php echo e($dotColor); ?> shrink-0"></div>
                                    <span class="text-sm text-gray-700 flex-1 min-w-0"><?php echo e($activity['activity']); ?></span>
                                    <span class="text-xs text-gray-400 whitespace-nowrap shrink-0">
                                        <?php echo e(explode(' – ', $activity['time'])[0]); ?>

                                    </span>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div x-show="showDetails" x-transition class="px-5 py-4 space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $schedules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $schedule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalbdad685aabe6e339c658c4ae9304d33e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbdad685aabe6e339c658c4ae9304d33e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.schedule-card','data' => ['schedule' => $schedule,'index' => $index]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.schedule-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['schedule' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($schedule),'index' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($index)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbdad685aabe6e339c658c4ae9304d33e)): ?>
<?php $attributes = $__attributesOriginalbdad685aabe6e339c658c4ae9304d33e; ?>
<?php unset($__attributesOriginalbdad685aabe6e339c658c4ae9304d33e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbdad685aabe6e339c658c4ae9304d33e)): ?>
<?php $component = $__componentOriginalbdad685aabe6e339c658c4ae9304d33e; ?>
<?php unset($__componentOriginalbdad685aabe6e339c658c4ae9304d33e); ?>
<?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="text-sm text-gray-400 text-center py-2"><?php echo e(__('No scheduled activity.')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/schedule-mini-overview.blade.php ENDPATH**/ ?>