<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('breadcrumbs', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala557792f98c3d5e43a5ea0d2a136e11a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala557792f98c3d5e43a5ea0d2a136e11a = $attributes; } ?>
<?php $component = Mary\View\Components\Breadcrumbs::resolve(['items' => [['label' => 'Dashboard']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Breadcrumbs::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala557792f98c3d5e43a5ea0d2a136e11a)): ?>
<?php $attributes = $__attributesOriginala557792f98c3d5e43a5ea0d2a136e11a; ?>
<?php unset($__attributesOriginala557792f98c3d5e43a5ea0d2a136e11a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala557792f98c3d5e43a5ea0d2a136e11a)): ?>
<?php $component = $__componentOriginala557792f98c3d5e43a5ea0d2a136e11a; ?>
<?php unset($__componentOriginala557792f98c3d5e43a5ea0d2a136e11a); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <div class="p-4 sm:p-6 space-y-5"
         x-data="{ feedFilter: 'all' }">

        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">Bonjour, <?php echo e(Auth::user()->first_name); ?> 👋</h1>
                <p class="text-sm text-base-content/50 mt-0.5"><?php echo e(now()->translatedFormat('l j F Y')); ?></p>
            </div>
        </div>

        
        <div class="flex flex-wrap gap-2 items-center">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($members_unpaid > 0): ?>
            <a href="#" class="inline-flex items-center gap-1.5 bg-warning/15 hover:bg-warning/25 text-warning-content border border-warning/30 rounded-full px-3 py-1 text-xs font-medium transition-colors">
                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-exclamation-triangle'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-3.5 h-3.5 text-warning']); ?>
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
                <?php echo e($members_unpaid); ?> cotisations impayées
            </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($interclubs_pending > 0): ?>
            <a href="#" class="inline-flex items-center gap-1.5 bg-error/10 hover:bg-error/20 text-error border border-error/20 rounded-full px-3 py-1 text-xs font-medium transition-colors">
                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-exclamation-circle'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-3.5 h-3.5']); ?>
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
                <?php echo e($interclubs_pending); ?> sélections manquantes
            </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($affiliations_pending > 0): ?>
            <a href="#" class="inline-flex items-center gap-1.5 bg-info/10 hover:bg-info/20 text-info border border-info/20 rounded-full px-3 py-1 text-xs font-medium transition-colors">
                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-document-text'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-3.5 h-3.5']); ?>
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
                <?php echo e($affiliations_pending); ?> affiliations en attente
            </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <span class="inline-flex items-center gap-1.5 bg-success/10 text-success border border-success/20 rounded-full px-3 py-1 text-xs font-medium">
                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-check-circle'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-3.5 h-3.5']); ?>
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
                Salles OK
            </span>
        </div>

        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-md transition-all group">
                <div class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg p-2 shrink-0">
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-users'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
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
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none"><?php echo e($members_total); ?></p>
                    <p class="text-xs text-base-content/50 truncate">Membres</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-violet-300 dark:hover:border-violet-700 hover:shadow-md transition-all group">
                <div class="bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 rounded-lg p-2 shrink-0">
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-trophy'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
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
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none"><?php echo e($teams_count); ?></p>
                    <p class="text-xs text-base-content/50 truncate"><?php echo e(__('Teams')); ?></p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-rose-300 dark:hover:border-rose-700 hover:shadow-md transition-all group">
                <div class="bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 rounded-lg p-2 shrink-0">
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-globe-alt'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
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
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none"><?php echo e($interclubs_pending); ?></p>
                    <p class="text-xs text-base-content/50 truncate">Interclubs</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-amber-300 dark:hover:border-amber-700 hover:shadow-md transition-all group">
                <div class="bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg p-2 shrink-0">
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-building-office-2'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
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
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none"><?php echo e($rooms_count); ?></p>
                    <p class="text-xs text-base-content/50 truncate">Salles</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-yellow-300 dark:hover:border-yellow-700 hover:shadow-md transition-all group">
                <div class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 rounded-lg p-2 shrink-0">
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-banknotes'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
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
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none"><?php echo e($payments_pending); ?></p>
                    <p class="text-xs text-base-content/50 truncate">Paiements</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md transition-all group">
                <div class="bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg p-2 shrink-0">
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-clock'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
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
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none"><?php echo e($trainings_count); ?></p>
                    <p class="text-xs text-base-content/50 truncate"><?php echo e(__('Training')); ?></p>
                </div>
            </a>

        </div>

        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            
            <div class="lg:col-span-2 space-y-4">

                <?php
                    $personaGroups = [
                        [
                            'label'  => __('Secretary'),
                            'count'  => 8,
                            'color'  => 'blue',
                            'tiles'  => [
                                ['icon' => 'o-users',         'label' => 'Membres',      'sub' => $members_total . ' au total',          'color' => 'blue'],
                                ['icon' => 'o-user-plus',     'label' => 'Inscriptions', 'sub' => 'Nouvelles demandes',                  'color' => 'cyan'],
                                ['icon' => 'o-document-text', 'label' => 'Affiliations', 'sub' => $affiliations_pending . ' en attente', 'color' => 'indigo', 'badge' => $affiliations_pending],
                                ['icon' => 'o-newspaper',     'label' => __('News'),   'sub' => 'Articles & news',                     'color' => 'slate'],
                                ['icon' => 'o-envelope',      'label' => 'Contacts',     'sub' => __('Received messages'),                      'color' => 'teal'],
                                ['icon' => 'o-calendar-days', 'label' => __('Meetings'),     'sub' => 'Comptes rendus',                      'color' => 'purple'],
                                ['icon' => 'o-calendar',      'label' => __('Events'),   'sub' => $events_count . ' en cours',           'color' => 'pink'],
                                ['icon' => 'o-cog-6-tooth',   'label' => __('Settings'),   'sub' => 'Club & saisons',                      'color' => 'gray'],
                            ],
                        ],
                        [
                            'label'  => __('Treasurer'),
                            'count'  => 6,
                            'color'  => 'amber',
                            'tiles'  => [
                                ['icon' => 'o-banknotes',               'label' => 'Paiements',      'sub' => $payments_pending . ' en attente', 'color' => 'yellow', 'badge' => $payments_pending],
                                ['icon' => 'o-credit-card',             'label' => 'Transactions',   'sub' => __('Bank statements'),               'color' => 'teal'],
                                ['icon' => 'o-receipt-percent',         'label' => 'Cotisations',    'sub' => $members_unpaid . ' impayées',     'color' => 'amber',  'badge' => $members_unpaid],
                                ['icon' => 'o-clipboard-document-list', 'label' => 'Abonnements',   'sub' => 'Saisons en cours',                'color' => 'indigo'],
                                ['icon' => 'o-document-chart-bar',      'label' => 'Rapport',        'sub' => __('Financial overview'),                'color' => 'slate'],
                                ['icon' => 'o-scale',                   'label' => __('Reconciliation'), 'sub' => __('Balances & verifications'),          'color' => 'gray'],
                            ],
                        ],
                        [
                            'label'  => __('Captain / Selector'),
                            'count'  => 6,
                            'color'  => 'rose',
                            'tiles'  => [
                                ['icon' => 'o-trophy',                       'label' => __('Teams'),     'sub' => $teams_count . ' équipes',              'color' => 'violet'],
                                ['icon' => 'o-globe-alt',                    'label' => 'Interclubs',  'sub' => $interclubs_pending . ' en attente',    'color' => 'rose', 'badge' => $interclubs_pending],
                                ['icon' => 'o-clipboard-document-check',     'label' => __('Selections'),  'sub' => 'Compositions',                         'color' => 'orange'],
                                ['icon' => 'o-chart-bar',                    'label' => __('Results'),   'sub' => 'Scores & classements',                 'color' => 'blue'],
                                ['icon' => 'o-calendar-days',                'label' => 'Planning',    'sub' => 'Calendrier matchs',                    'color' => 'emerald'],
                                ['icon' => 'o-user-group',                   'label' => 'Joueurs',     'sub' => $members_competitors . ' compétiteurs', 'color' => 'purple'],
                            ],
                        ],
                        [
                            'label'  => __('Committee'),
                            'count'  => 8,
                            'color'  => 'violet',
                            'tiles'  => [
                                ['icon' => 'o-users',             'label' => 'Membres',        'sub' => $members_total . ' inscrits',    'color' => 'blue'],
                                ['icon' => 'o-building-office-2', 'label' => 'Salles',         'sub' => $rooms_count . ' installations', 'color' => 'amber'],
                                ['icon' => 'o-clock',             'label' => __('Training sessions'),  'sub' => $trainings_count . ' séances',   'color' => 'emerald'],
                                ['icon' => 'o-calendar-days',     'label' => 'Saisons',        'sub' => __('Period management'),          'color' => 'indigo'],
                                ['icon' => 'o-newspaper',         'label' => __('News'),     'sub' => 'Site public',                   'color' => 'slate'],
                                ['icon' => 'o-megaphone',         'label' => __('Events'),     'sub' => $events_count . ' planifié(s)', 'color' => 'pink'],
                                ['icon' => 'o-calendar',          'label' => __('Meetings'),       'sub' => 'Comptes rendus',                'color' => 'purple'],
                                ['icon' => 'o-cog-6-tooth',       'label' => 'Configuration',  'sub' => __('Club settings'),               'color' => 'gray'],
                            ],
                        ],
                    ];
                ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $personaGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalfc1ffaf3591c8543322f2d8540286c33 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfc1ffaf3591c8543322f2d8540286c33 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.section-accordion','data' => ['label' => $group['label'],'count' => $group['count'] . ' accès','color' => $group['color']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('section-accordion'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($group['label']),'count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($group['count'] . ' accès'),'color' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($group['color'])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $group['tiles']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php echo $__env->make('clubAdmin._dashboard_tile', $tile, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfc1ffaf3591c8543322f2d8540286c33)): ?>
<?php $attributes = $__attributesOriginalfc1ffaf3591c8543322f2d8540286c33; ?>
<?php unset($__attributesOriginalfc1ffaf3591c8543322f2d8540286c33); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfc1ffaf3591c8543322f2d8540286c33)): ?>
<?php $component = $__componentOriginalfc1ffaf3591c8543322f2d8540286c33; ?>
<?php unset($__componentOriginalfc1ffaf3591c8543322f2d8540286c33); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            </div>

            
            <div class="lg:col-span-1 space-y-3">

                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider"><?php echo e(__('Recent activity')); ?></p>
                </div>

                
                <div class="flex gap-1 flex-wrap">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                        ['key' => 'all',       'label' => 'Tout'],
                        ['key' => 'member',    'label' => 'Membres'],
                        ['key' => 'payment',   'label' => 'Paiements'],
                        ['key' => 'match',     'label' => 'Matchs'],
                        ['key' => 'contact',   'label' => 'Contacts'],
                        ['key' => 'news',      'label' => 'News'],
                        ['key' => 'meeting',   'label' => __('Meetings')],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button
                        @click="feedFilter = '<?php echo e($f['key']); ?>'"
                        :class="feedFilter === '<?php echo e($f['key']); ?>' ? 'bg-neutral text-neutral-content' : 'bg-base-200 text-base-content/60 hover:bg-base-300'"
                        class="btn btn-xs rounded-full transition-all">
                        <?php echo e($f['label']); ?>

                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <div class="bg-base-100 rounded-xl border border-base-200 shadow-sm divide-y divide-base-200">

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $recent_activity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $cfg = [
                            'member'    => ['icon' => 'o-user',          'bg' => 'bg-blue-100 dark:bg-blue-900/30',    'text' => 'text-blue-600 dark:text-blue-400'],
                            'payment'   => ['icon' => 'o-banknotes',     'bg' => 'bg-yellow-100 dark:bg-yellow-900/30','text' => 'text-yellow-600 dark:text-yellow-400'],
                            'match'     => ['icon' => 'o-globe-alt',     'bg' => 'bg-rose-100 dark:bg-rose-900/30',    'text' => 'text-rose-600 dark:text-rose-400'],
                            'selection' => ['icon' => 'o-clipboard-document-check', 'bg' => 'bg-orange-100 dark:bg-orange-900/30', 'text' => 'text-orange-600 dark:text-orange-400'],
                            'contact'   => ['icon' => 'o-envelope',      'bg' => 'bg-teal-100 dark:bg-teal-900/30',   'text' => 'text-teal-600 dark:text-teal-400'],
                            'news'      => ['icon' => 'o-newspaper',     'bg' => 'bg-slate-100 dark:bg-slate-800/40', 'text' => 'text-slate-600 dark:text-slate-400'],
                            'meeting'   => ['icon' => 'o-calendar-days', 'bg' => 'bg-purple-100 dark:bg-purple-900/30','text' => 'text-purple-600 dark:text-purple-400'],
                        ][$item['type']] ?? ['icon' => 'o-bell', 'bg' => 'bg-base-200', 'text' => 'text-base-content'];
                    ?>
                    <div x-show="feedFilter === 'all' || feedFilter === '<?php echo e($item['type']); ?>'"
                         x-transition.opacity
                         class="flex items-start gap-3 px-3 py-2.5 hover:bg-base-200/50 transition-colors">
                        <div class="<?php echo e($cfg['bg']); ?> <?php echo e($cfg['text']); ?> rounded-full p-1.5 shrink-0 mt-0.5">
                            <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => ''.e($cfg['icon']).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-3.5 h-3.5']); ?>
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
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-base-content leading-snug"><?php echo e($item['label']); ?></p>
                        </div>
                        <span class="text-xs text-base-content/30 shrink-0 tabular-nums"><?php echo e($item['time']); ?></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                </div>

            </div>

        </div>

    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/clubAdmin/dashboard_v4_personas.blade.php ENDPATH**/ ?>