<div class="p-4 max-w-6xl mx-auto">
     <?php $__env->slot('breadcrumbs', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala557792f98c3d5e43a5ea0d2a136e11a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala557792f98c3d5e43a5ea0d2a136e11a = $attributes; } ?>
<?php $component = Mary\View\Components\Breadcrumbs::resolve(['items' => $breadcrumbs,'separator' => 'o-slash'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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

    <?php if (isset($component)) { $__componentOriginal6f99ffca722ef3c8789c4087c5ac9f0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f99ffca722ef3c8789c4087c5ac9f0d = $attributes; } ?>
<?php $component = Mary\View\Components\Header::resolve(['title' => ''.e($tournament->name).'','subtitle' => __('Live tournament management')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Header::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('actions', null, []); ?> 
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tournamentClosed): ?>
                <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => ''.e(__('Closed')).'','icon' => 'o-lock-closed'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'badge-neutral']); ?>
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
            <?php elseif($this->canManageTournament && $this->poolsPhaseComplete && ! $this->bracketExists): ?>
                <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['label' => __('Create bracket'),'icon' => 'o-trophy','spinner' => 'generateBracket'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-warning btn-sm animate-pulse','wire:click' => 'generateBracket']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal602b228a887fab12f0012a3179e5b533)): ?>
<?php $attributes = $__attributesOriginal602b228a887fab12f0012a3179e5b533; ?>
<?php unset($__attributesOriginal602b228a887fab12f0012a3179e5b533); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal602b228a887fab12f0012a3179e5b533)): ?>
<?php $component = $__componentOriginal602b228a887fab12f0012a3179e5b533; ?>
<?php unset($__componentOriginal602b228a887fab12f0012a3179e5b533); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f99ffca722ef3c8789c4087c5ac9f0d)): ?>
<?php $attributes = $__attributesOriginal6f99ffca722ef3c8789c4087c5ac9f0d; ?>
<?php unset($__attributesOriginal6f99ffca722ef3c8789c4087c5ac9f0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f99ffca722ef3c8789c4087c5ac9f0d)): ?>
<?php $component = $__componentOriginal6f99ffca722ef3c8789c4087c5ac9f0d; ?>
<?php unset($__componentOriginal6f99ffca722ef3c8789c4087c5ac9f0d); ?>
<?php endif; ?>

    
    <div class="flex items-center gap-0 mb-8 mt-2 select-none overflow-x-auto">

        <?php
            $phases = [
                ['label' => __('Pools'),   'done' => $this->poolsPhaseComplete,   'active' => ! $this->poolsPhaseComplete],
                ['label' => __('Bracket'), 'done' => $this->bracketPhaseComplete, 'active' => $this->poolsPhaseComplete && ! $this->bracketPhaseComplete],
            ];
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $phases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $phase): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="flex flex-col items-center gap-1 shrink-0">
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all',
                    'bg-success border-success text-success-content'                              => $phase['done'],
                    'bg-primary border-primary text-primary-content ring-4 ring-primary/20'       => $phase['active'] && ! $phase['done'],
                    'bg-base-200 border-base-300 text-base-content/30'                           => ! $phase['done'] && ! $phase['active'],
                ]); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($phase['done']): ?>
                        <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-check'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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
                    <?php elseif($phase['active']): ?>
                        <span class="loading loading-ring loading-xs"></span>
                    <?php else: ?>
                        <span class="text-xs font-bold"><?php echo e($i + 1); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'text-xs font-semibold whitespace-nowrap',
                    'text-success'         => $phase['done'],
                    'text-primary'         => $phase['active'] && ! $phase['done'],
                    'text-base-content/30' => ! $phase['done'] && ! $phase['active'],
                ]); ?>"><?php echo e($phase['label']); ?></span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $loop->last): ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'flex-1 h-0.5 mx-2 min-w-[40px] transition-all',
                    'bg-success'  => $phases[$i + 1]['done'] || $phases[$i + 1]['active'],
                    'bg-base-300' => ! $phases[$i + 1]['done'] && ! $phases[$i + 1]['active'],
                ]); ?>"></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <?php if (isset($component)) { $__componentOriginal04627fcb0bca4a40e5e6a695e202d6e4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04627fcb0bca4a40e5e6a695e202d6e4 = $attributes; } ?>
<?php $component = Mary\View\Components\Tabs::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tabs::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'activeTab','class' => 'mb-6']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>


        <?php if (isset($component)) { $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $attributes; } ?>
<?php $component = Mary\View\Components\Tab::resolve(['name' => 'pools','icon' => 'o-user-group'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tab::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('label', null, []); ?> <?php echo e(__('Pools')); ?> <?php $__env->endSlot(); ?>
            <?php echo $__env->make('admin.club-events.tournaments.partials.live.tabs.pools', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $attributes = $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $component = $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canManageTournament): ?>
        <?php if (isset($component)) { $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $attributes; } ?>
<?php $component = Mary\View\Components\Tab::resolve(['name' => 'tables','icon' => 'o-squares-2x2'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tab::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('label', null, []); ?> 
                <?php echo e(__('Tables')); ?>

                <?php $inProgress = $this->tables->flatten(1)->where('is_free', false)->count(); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inProgress > 0): ?>
                    <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => ''.e($inProgress).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ml-1 badge-primary badge-sm']); ?>
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
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php $__env->endSlot(); ?>
            <?php echo $__env->make('admin.club-events.tournaments.partials.live.tabs.tables', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $attributes = $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $component = $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if (isset($component)) { $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $attributes; } ?>
<?php $component = Mary\View\Components\Tab::resolve(['name' => 'upcoming','icon' => 'o-megaphone'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tab::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('label', null, []); ?> 
                <?php echo e(__('Upcoming')); ?>

                <?php $upcomingCount = $this->upcomingMatches->count(); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($upcomingCount > 0): ?>
                    <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => ''.e($upcomingCount).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ml-1 badge-ghost badge-sm']); ?>
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
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php $__env->endSlot(); ?>
            <?php echo $__env->make('admin.club-events.tournaments.partials.live.tabs.upcoming', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $attributes = $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $component = $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $attributes; } ?>
<?php $component = Mary\View\Components\Tab::resolve(['name' => 'bracket','icon' => 'o-trophy'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tab::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('label', null, []); ?> <?php echo e(__('Bracket')); ?> <?php $__env->endSlot(); ?>
            <?php echo $__env->make('admin.club-events.tournaments.partials.live.tabs.bracket', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $attributes = $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $component = $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $attributes; } ?>
<?php $component = Mary\View\Components\Tab::resolve(['name' => 'rankings','icon' => 'o-chart-bar'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tab::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('label', null, []); ?> <?php echo e(__('Rankings')); ?> <?php $__env->endSlot(); ?>
            <?php echo $__env->make('admin.club-events.tournaments.partials.live.tabs.rankings', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $attributes = $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $component = $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canManageTournament): ?>
        <?php if (isset($component)) { $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb = $attributes; } ?>
<?php $component = Mary\View\Components\Tab::resolve(['name' => 'closure','icon' => 'o-lock-closed'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tab'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Tab::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('label', null, []); ?> 
                <?php echo e(__('Closure')); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bracketPhaseComplete && ! $this->tournamentClosed): ?>
                    <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => '!'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ml-1 badge-error badge-xs']); ?>
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
                <?php elseif($this->tournamentClosed): ?>
                    <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-check-circle'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ml-1 w-3.5 h-3.5 text-success inline']); ?>
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
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php $__env->endSlot(); ?>
            <?php echo $__env->make('admin.club-events.tournaments.partials.live.tabs.closure', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $attributes = $__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__attributesOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb)): ?>
<?php $component = $__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb; ?>
<?php unset($__componentOriginalb493c5e8eb9746e55ffcf8e6d36bf5cb); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04627fcb0bca4a40e5e6a695e202d6e4)): ?>
<?php $attributes = $__attributesOriginal04627fcb0bca4a40e5e6a695e202d6e4; ?>
<?php unset($__attributesOriginal04627fcb0bca4a40e5e6a695e202d6e4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04627fcb0bca4a40e5e6a695e202d6e4)): ?>
<?php $component = $__componentOriginal04627fcb0bca4a40e5e6a695e202d6e4; ?>
<?php unset($__componentOriginal04627fcb0bca4a40e5e6a695e202d6e4); ?>
<?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canManageTournament): ?>
        <?php echo $__env->make('admin.club-events.tournaments.partials.live.drawers.score-entry', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('admin.club-events.tournaments.partials.live.drawers.launch-match', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/pages/club-events/tournaments/⚡live-center/live-center.blade.php ENDPATH**/ ?>