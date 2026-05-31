<section id="schedule" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4"><?php echo e(__('Schedule and activities')); ?></h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Rejoignez-nous pour nos séances d'entraînement régulières et nos tournois, bienvenues à tous les supporters les vendredis soir.
            </p>
        </div>

        <!-- Affiché uniquement sur les smartphones -->
        <div class="block md:hidden">
            <?php if (isset($component)) { $__componentOriginal24409c5ac6e6f8f06a048cce081d1027 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal24409c5ac6e6f8f06a048cce081d1027 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.schedule-mini-overview','data' => ['schedules' => $schedules ?? [],'compact' => false]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.schedule-mini-overview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['schedules' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($schedules ?? []),'compact' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal24409c5ac6e6f8f06a048cce081d1027)): ?>
<?php $attributes = $__attributesOriginal24409c5ac6e6f8f06a048cce081d1027; ?>
<?php unset($__attributesOriginal24409c5ac6e6f8f06a048cce081d1027); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal24409c5ac6e6f8f06a048cce081d1027)): ?>
<?php $component = $__componentOriginal24409c5ac6e6f8f06a048cce081d1027; ?>
<?php unset($__componentOriginal24409c5ac6e6f8f06a048cce081d1027); ?>
<?php endif; ?>
        </div>

        <!-- Affiché uniquement sur les tablettes (iPad par ex.) -->
        <div class="hidden md:block lg:hidden">
            <?php if (isset($component)) { $__componentOriginalc9ea47be14b07553a15aaec1dbbb22b7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc9ea47be14b07553a15aaec1dbbb22b7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.schedule-week-overview','data' => ['schedules' => $schedules ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.schedule-week-overview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['schedules' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($schedules ?? [])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc9ea47be14b07553a15aaec1dbbb22b7)): ?>
<?php $attributes = $__attributesOriginalc9ea47be14b07553a15aaec1dbbb22b7; ?>
<?php unset($__attributesOriginalc9ea47be14b07553a15aaec1dbbb22b7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc9ea47be14b07553a15aaec1dbbb22b7)): ?>
<?php $component = $__componentOriginalc9ea47be14b07553a15aaec1dbbb22b7; ?>
<?php unset($__componentOriginalc9ea47be14b07553a15aaec1dbbb22b7); ?>
<?php endif; ?>
        </div>

        <!-- Affiché uniquement sur les ordinateurs -->
        <div class="hidden lg:block">
            <?php if (isset($component)) { $__componentOriginal2dac2c45d585794902c25691b0c3b261 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2dac2c45d585794902c25691b0c3b261 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.schedule-calendar-view','data' => ['schedules' => $schedules ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.schedule-calendar-view'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['schedules' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($schedules ?? [])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2dac2c45d585794902c25691b0c3b261)): ?>
<?php $attributes = $__attributesOriginal2dac2c45d585794902c25691b0c3b261; ?>
<?php unset($__attributesOriginal2dac2c45d585794902c25691b0c3b261); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2dac2c45d585794902c25691b0c3b261)): ?>
<?php $component = $__componentOriginal2dac2c45d585794902c25691b0c3b261; ?>
<?php unset($__componentOriginal2dac2c45d585794902c25691b0c3b261); ?>
<?php endif; ?>
        </div>
        
        <!-- Call to Action -->
        <div class="text-center mt-12 animate-on-scroll">
            <div class="bg-gradient-to-r from-club-blue to-club-blue-light rounded-2xl p-8 text-white">
                <h3 class="text-2xl font-bold mb-4"><?php echo e(__('Ready to get started?')); ?></h3>
                <p class="text-xl mb-6 opacity-90">
                    Rejoignez-nous pour une séance d'essai gratuite !
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#contact" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                        Réserver une Séance d'Essai
                    </a>
                    <a href="#join" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                        Devenir Membre
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/schedule-section.blade.php ENDPATH**/ ?>