<div>
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
<?php $component = Mary\View\Components\Header::resolve(['progressIndicator' => true,'separator' => true,'title' => __('Events')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Header::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('middle', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['clearable' => true,'icon' => 'o-magnifying-glass'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-full','placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Search...')),'wire:model.live.debounce.300ms' => 'search']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
         <?php $__env->endSlot(); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost '.e($this->activeFiltersCount > 0 ? 'btn-active' : '').'','wire:click' => '$toggle(\'showFilters\')']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-funnel'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-5 w-5']); ?>
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
                <?php echo e(__('Filters')); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->activeFiltersCount > 0): ?>
                    <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => ''.e($this->activeFiltersCount).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'badge-sm badge-primary']); ?>
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

    
    <?php if (isset($component)) { $__componentOriginal908123f6c9c3798a20bfbc6617529d4b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal908123f6c9c3798a20bfbc6617529d4b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.shared.filter-bar','data' => ['activeFiltersCount' => $this->activeFiltersCount,'show' => $showFilters]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.shared.filter-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active-filters-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($this->activeFiltersCount),'show' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showFilters)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

         <?php $__env->slot('filters', null, []); ?> 
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    <?php echo e(__('Status')); ?>

                </p>
                <?php if (isset($component)) { $__componentOriginald64144c2287634503c73cd4803d6e578 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald64144c2287634503c73cd4803d6e578 = $attributes; } ?>
<?php $component = Mary\View\Components\Select::resolve(['options' => $statusOptions,'placeholder' => __('All statuses')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Select::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'status','class' => 'w-full']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald64144c2287634503c73cd4803d6e578)): ?>
<?php $attributes = $__attributesOriginald64144c2287634503c73cd4803d6e578; ?>
<?php unset($__attributesOriginald64144c2287634503c73cd4803d6e578); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald64144c2287634503c73cd4803d6e578)): ?>
<?php $component = $__componentOriginald64144c2287634503c73cd4803d6e578; ?>
<?php unset($__componentOriginald64144c2287634503c73cd4803d6e578); ?>
<?php endif; ?>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    <?php echo e(__('Type')); ?>

                </p>
                <?php if (isset($component)) { $__componentOriginald64144c2287634503c73cd4803d6e578 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald64144c2287634503c73cd4803d6e578 = $attributes; } ?>
<?php $component = Mary\View\Components\Select::resolve(['options' => $typeOptions,'placeholder' => __('All types')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Select::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'type','class' => 'w-full']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald64144c2287634503c73cd4803d6e578)): ?>
<?php $attributes = $__attributesOriginald64144c2287634503c73cd4803d6e578; ?>
<?php unset($__attributesOriginald64144c2287634503c73cd4803d6e578); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald64144c2287634503c73cd4803d6e578)): ?>
<?php $component = $__componentOriginald64144c2287634503c73cd4803d6e578; ?>
<?php unset($__componentOriginald64144c2287634503c73cd4803d6e578); ?>
<?php endif; ?>
            </div>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal908123f6c9c3798a20bfbc6617529d4b)): ?>
<?php $attributes = $__attributesOriginal908123f6c9c3798a20bfbc6617529d4b; ?>
<?php unset($__attributesOriginal908123f6c9c3798a20bfbc6617529d4b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal908123f6c9c3798a20bfbc6617529d4b)): ?>
<?php $component = $__componentOriginal908123f6c9c3798a20bfbc6617529d4b; ?>
<?php unset($__componentOriginal908123f6c9c3798a20bfbc6617529d4b); ?>
<?php endif; ?>

    
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?php
            $statCards = [
                ['label' => __('Total'),     'key' => 'total',     'icon' => 'o-calendar-days', 'bg' => 'bg-base-200',   'color' => 'text-base-content/60'],
                ['label' => __('Published'), 'key' => 'published', 'icon' => 'o-check-circle',  'bg' => 'bg-success/10', 'color' => 'text-success'],
                ['label' => __('Draft'),     'key' => 'draft',     'icon' => 'o-pencil-square',  'bg' => 'bg-warning/10', 'color' => 'text-warning'],
                ['label' => __('Archived'),  'key' => 'archived',  'icon' => 'o-archive-box',   'bg' => 'bg-base-200',   'color' => 'text-base-content/30'],
            ];
        ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $statCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal7f194736b6f6432dc38786f292496c34 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7f194736b6f6432dc38786f292496c34 = $attributes; } ?>
<?php $component = Mary\View\Components\Card::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Card::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'shadow-sm']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl <?php echo e($card['bg']); ?>">
                        <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => ''.e($card['icon']).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-5 w-5 '.e($card['color']).'']); ?>
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
                    <div>
                        <p class="text-2xl font-bold <?php echo e($card['color']); ?>"><?php echo e($stats[$card['key']] ?? 0); ?></p>
                        <p class="text-xs text-base-content/40"><?php echo e($card['label']); ?></p>
                    </div>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7f194736b6f6432dc38786f292496c34)): ?>
<?php $attributes = $__attributesOriginal7f194736b6f6432dc38786f292496c34; ?>
<?php unset($__attributesOriginal7f194736b6f6432dc38786f292496c34); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7f194736b6f6432dc38786f292496c34)): ?>
<?php $component = $__componentOriginal7f194736b6f6432dc38786f292496c34; ?>
<?php unset($__componentOriginal7f194736b6f6432dc38786f292496c34); ?>
<?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $statusBadge = match ($event->status) {
                    \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED => ['class' => 'badge-success badge-soft', 'label' => __('Published')],
                    \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT     => ['class' => 'badge-warning badge-soft', 'label' => __('Draft')],
                    \App\Domains\Shared\Enums\EventPostStatusEnum::ARCHIVED  => ['class' => 'badge-ghost',              'label' => __('Archived')],
                };
            ?>
            <?php if (isset($component)) { $__componentOriginal8653fe0e2b5ee7b7ab3811c66ab90418 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8653fe0e2b5ee7b7ab3811c66ab90418 = $attributes; } ?>
<?php $component = Mary\View\Components\ListItem::resolve(['item' => $event] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('list-item'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\ListItem::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'bg-base-100 rounded-lg border','wire:key' => 'mobile-event-'.e($event->id).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                 <?php $__env->slot('value', null, []); ?> 
                    <span class="font-medium"><?php echo e($event->title); ?></span>
                 <?php $__env->endSlot(); ?>
                 <?php $__env->slot('subValue', null, []); ?> 
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => $statusBadge['label']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => ''.e($statusBadge['class']).' badge-sm']); ?>
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
                        <span class="text-xs text-base-content/40">
                            <?php echo e($event->type->getIcon()); ?> <?php echo e($event->type->getLabel()); ?>

                        </span>
                        <span class="text-xs text-base-content/40">
                            <?php echo e($event->event_date->translatedFormat('d M Y')); ?>

                        </span>
                    </div>
                 <?php $__env->endSlot(); ?>
                 <?php $__env->slot('actions', null, []); ?> 
                    <?php if (isset($component)) { $__componentOriginal1939ee20476ffee66548eac2c6216616 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1939ee20476ffee66548eac2c6216616 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.shared.row-actions','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.shared.row-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT): ?>
                            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-check-circle','tooltip' => __('Publish'),'spinner' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle text-success','wire:click' => 'publish('.e($event->id).')']); ?>
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
                        <?php elseif($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED): ?>
                            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-archive-box','tooltip' => __('Archive'),'spinner' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle text-base-content/40','wire:click' => 'archive('.e($event->id).')']); ?>
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
                        <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-pencil','tooltip' => __('Edit')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle','wire:click' => 'openEdit('.e($event->id).')']); ?>
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->canBeDeleted()): ?>
                            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-trash','tooltip' => __('Delete')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle text-error','wire:click' => 'confirmDelete('.e($event->id).')']); ?>
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
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1939ee20476ffee66548eac2c6216616)): ?>
<?php $attributes = $__attributesOriginal1939ee20476ffee66548eac2c6216616; ?>
<?php unset($__attributesOriginal1939ee20476ffee66548eac2c6216616); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1939ee20476ffee66548eac2c6216616)): ?>
<?php $component = $__componentOriginal1939ee20476ffee66548eac2c6216616; ?>
<?php unset($__componentOriginal1939ee20476ffee66548eac2c6216616); ?>
<?php endif; ?>
                 <?php $__env->endSlot(); ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8653fe0e2b5ee7b7ab3811c66ab90418)): ?>
<?php $attributes = $__attributesOriginal8653fe0e2b5ee7b7ab3811c66ab90418; ?>
<?php unset($__attributesOriginal8653fe0e2b5ee7b7ab3811c66ab90418); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8653fe0e2b5ee7b7ab3811c66ab90418)): ?>
<?php $component = $__componentOriginal8653fe0e2b5ee7b7ab3811c66ab90418; ?>
<?php unset($__componentOriginal8653fe0e2b5ee7b7ab3811c66ab90418); ?>
<?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'o-calendar-days','heading' => __('No events found'),'message' => __('Try adjusting your search or filters.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'o-calendar-days','heading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('No events found')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Try adjusting your search or filters.'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="hidden lg:block">
        <?php if (isset($component)) { $__componentOriginal7f194736b6f6432dc38786f292496c34 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7f194736b6f6432dc38786f292496c34 = $attributes; } ?>
<?php $component = Mary\View\Components\Card::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Card::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($events->isEmpty()): ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['icon' => 'o-calendar-days','heading' => __('No events found'),'message' => __('Try adjusting your search or filters.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'o-calendar-days','heading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('No events found')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Try adjusting your search or filters.'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal8fbd727209323874b055feef49197909 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8fbd727209323874b055feef49197909 = $attributes; } ?>
<?php $component = Mary\View\Components\Table::resolve(['headers' => $headers,'rows' => $events,'sortBy' => $sortBy] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Table::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('cell_type', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <span class="inline-flex items-center gap-1.5 text-sm">
                            <span><?php echo e($event->type->getIcon()); ?></span>
                            <span class="text-base-content/60"><?php echo e($event->type->getLabel()); ?></span>
                        </span>
                    <?php }); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('cell_title', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <div>
                            <span class="font-medium"><?php echo e($event->title); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->featured): ?>
                                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-star'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ml-1 inline h-3.5 w-3.5 text-warning']); ?>
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
                        </div>
                    <?php }); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('cell_event_date', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <div class="text-sm text-base-content/60">
                            <div><?php echo e($event->event_date->translatedFormat('d M Y')); ?></div>
                            <div class="text-xs opacity-60"><?php echo e($event->start_time?->format('H:i')); ?></div>
                        </div>
                    <?php }); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('cell_eventable', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <span class="text-sm text-base-content/60">
                            <?php echo e($event->eventable?->name ?? '—'); ?>

                        </span>
                    <?php }); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('cell_status', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <?php
                            $s = match ($event->status) {
                                \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED => ['class' => 'badge-success badge-soft', 'label' => __('Published')],
                                \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT     => ['class' => 'badge-warning badge-soft', 'label' => __('Draft')],
                                \App\Domains\Shared\Enums\EventPostStatusEnum::ARCHIVED  => ['class' => 'badge-ghost',              'label' => __('Archived')],
                            };
                        ?>
                        <?php if (isset($component)) { $__componentOriginal4f015fb6508e425790bdb8f79792e6ed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f015fb6508e425790bdb8f79792e6ed = $attributes; } ?>
<?php $component = Mary\View\Components\Badge::resolve(['value' => $s['label']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Badge::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => ''.e($s['class']).'']); ?>
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
                    <?php }); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('cell_featured', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->featured): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->featured_until && $event->featured_until->isPast()): ?>
                                <span class="text-xs text-base-content/30 italic"><?php echo e(__('Expired')); ?></span>
                            <?php else: ?>
                                <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-star'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4 text-warning']); ?>
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->featured_until): ?>
                                    <span class="ml-1 text-xs text-base-content/40">
                                        <?php echo e(__('until')); ?> <?php echo e($event->featured_until->translatedFormat('d M')); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php else: ?>
                            <span class="text-base-content/20">—</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php }); ?>

                    <?php $__bladeCompiler = $__bladeCompiler ?? null; $loop = null; $__env->slot('actions', function($event) use ($__env,$__bladeCompiler) { $loop = (object) $__env->getLoopStack()[0] ?>
                        <?php if (isset($component)) { $__componentOriginal1939ee20476ffee66548eac2c6216616 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1939ee20476ffee66548eac2c6216616 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.shared.row-actions','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.shared.row-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT): ?>
                                <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-check-circle','tooltip' => __('Publish'),'spinner' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle text-success','wire:click' => 'publish('.e($event->id).')']); ?>
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
                            <?php elseif($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED): ?>
                                <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-archive-box','tooltip' => __('Archive'),'spinner' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle text-base-content/40','wire:click' => 'archive('.e($event->id).')']); ?>
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
                            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-pencil','tooltip' => __('Edit')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle','wire:click' => 'openEdit('.e($event->id).')']); ?>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->canBeDeleted()): ?>
                                <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-trash','tooltip' => __('Delete')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-sm btn-circle text-error','wire:click' => 'confirmDelete('.e($event->id).')']); ?>
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
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1939ee20476ffee66548eac2c6216616)): ?>
<?php $attributes = $__attributesOriginal1939ee20476ffee66548eac2c6216616; ?>
<?php unset($__attributesOriginal1939ee20476ffee66548eac2c6216616); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1939ee20476ffee66548eac2c6216616)): ?>
<?php $component = $__componentOriginal1939ee20476ffee66548eac2c6216616; ?>
<?php unset($__componentOriginal1939ee20476ffee66548eac2c6216616); ?>
<?php endif; ?>
                    <?php }); ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8fbd727209323874b055feef49197909)): ?>
<?php $attributes = $__attributesOriginal8fbd727209323874b055feef49197909; ?>
<?php unset($__attributesOriginal8fbd727209323874b055feef49197909); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8fbd727209323874b055feef49197909)): ?>
<?php $component = $__componentOriginal8fbd727209323874b055feef49197909; ?>
<?php unset($__componentOriginal8fbd727209323874b055feef49197909); ?>
<?php endif; ?>
                <div class="mt-4">
                    <?php echo e($events->links()); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7f194736b6f6432dc38786f292496c34)): ?>
<?php $attributes = $__attributesOriginal7f194736b6f6432dc38786f292496c34; ?>
<?php unset($__attributesOriginal7f194736b6f6432dc38786f292496c34); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7f194736b6f6432dc38786f292496c34)): ?>
<?php $component = $__componentOriginal7f194736b6f6432dc38786f292496c34; ?>
<?php unset($__componentOriginal7f194736b6f6432dc38786f292496c34); ?>
<?php endif; ?>
    </div>

    
    <?php if (isset($component)) { $__componentOriginal40f76302556bfbc1c9486f0510e50a96 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40f76302556bfbc1c9486f0510e50a96 = $attributes; } ?>
<?php $component = Mary\View\Components\Drawer::resolve(['title' => __('Edit event'),'right' => true,'withCloseButton' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('drawer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Drawer::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editDrawer','class' => 'w-full max-w-lg']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedEvent): ?>
            <div class="space-y-5 p-1">

                
                <div class="flex items-center gap-2 rounded-lg bg-base-200 px-3 py-2 text-sm">
                    <span><?php echo e($selectedEvent->type->getIcon()); ?></span>
                    <span class="font-medium"><?php echo e($selectedEvent->type->getLabel()); ?></span>
                    <span class="text-base-content/40">·</span>
                    <span class="text-base-content/60"><?php echo e($selectedEvent->eventable?->name ?? '—'); ?></span>
                </div>

                
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                        <?php echo e(__('Status')); ?>

                    </p>
                    <div class="flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                            ['DRAFT',     __('Draft'),     'btn-warning'],
                            ['PUBLISHED', __('Published'), 'btn-success'],
                            ['ARCHIVED',  __('Archived'),  'btn-ghost'],
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val, $label, $cls]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['label' => $label] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-sm btn-soft '.e($cls).' '.e($editStatus === $val ? 'opacity-100' : 'opacity-40').'','wire:click' => '$set(\'editStatus\', \''.e($val).'\')']); ?>
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>

                
                <div class="space-y-3">
                    <?php if (isset($component)) { $__componentOriginal91586e22c1998368a30f831eea05043a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91586e22c1998368a30f831eea05043a = $attributes; } ?>
<?php $component = Mary\View\Components\Toggle::resolve(['label' => __('Featured on website')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Toggle::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'editFeatured']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91586e22c1998368a30f831eea05043a)): ?>
<?php $attributes = $__attributesOriginal91586e22c1998368a30f831eea05043a; ?>
<?php unset($__attributesOriginal91586e22c1998368a30f831eea05043a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91586e22c1998368a30f831eea05043a)): ?>
<?php $component = $__componentOriginal91586e22c1998368a30f831eea05043a; ?>
<?php unset($__componentOriginal91586e22c1998368a30f831eea05043a); ?>
<?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editFeatured): ?>
                        <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Featured until'),'hint' => __('Leave empty to feature indefinitely')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editFeaturedUntil','type' => 'date','min' => ''.e(now()->format('Y-m-d')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Title')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editTitle']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginaleda28cbc945270b2059ee861cf34a6bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleda28cbc945270b2059ee861cf34a6bc = $attributes; } ?>
<?php $component = Mary\View\Components\Textarea::resolve(['label' => __('Description')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Textarea::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editDescription','rows' => '4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleda28cbc945270b2059ee861cf34a6bc)): ?>
<?php $attributes = $__attributesOriginaleda28cbc945270b2059ee861cf34a6bc; ?>
<?php unset($__attributesOriginaleda28cbc945270b2059ee861cf34a6bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleda28cbc945270b2059ee861cf34a6bc)): ?>
<?php $component = $__componentOriginaleda28cbc945270b2059ee861cf34a6bc; ?>
<?php unset($__componentOriginaleda28cbc945270b2059ee861cf34a6bc); ?>
<?php endif; ?>

                
                <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Location'),'icon' => 'o-map-pin'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editLocation']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>

                <div class="grid grid-cols-2 gap-3">
                    <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Date')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editEventDate','type' => 'date']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Start time')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editStartTime','type' => 'time']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
                </div>
                <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('End time'),'hint' => __('Optional')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editEndTime','type' => 'time']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Price'),'hint' => __('Leave empty if free'),'icon' => 'o-banknotes'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editPrice']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>

                
                <div class="grid grid-cols-3 gap-3">
                    <?php if (isset($component)) { $__componentOriginalf51438a7488970badd535e5f203e0c1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf51438a7488970badd535e5f203e0c1b = $attributes; } ?>
<?php $component = Mary\View\Components\Input::resolve(['label' => __('Icon'),'hint' => __('Emoji')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Input::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editIcon']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $attributes = $__attributesOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__attributesOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf51438a7488970badd535e5f203e0c1b)): ?>
<?php $component = $__componentOriginalf51438a7488970badd535e5f203e0c1b; ?>
<?php unset($__componentOriginalf51438a7488970badd535e5f203e0c1b); ?>
<?php endif; ?>
                    <div class="col-span-2">
                        <?php if (isset($component)) { $__componentOriginaleda28cbc945270b2059ee861cf34a6bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleda28cbc945270b2059ee861cf34a6bc = $attributes; } ?>
<?php $component = Mary\View\Components\Textarea::resolve(['label' => __('Notes')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Textarea::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'editNotes','rows' => '2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleda28cbc945270b2059ee861cf34a6bc)): ?>
<?php $attributes = $__attributesOriginaleda28cbc945270b2059ee861cf34a6bc; ?>
<?php unset($__attributesOriginaleda28cbc945270b2059ee861cf34a6bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleda28cbc945270b2059ee861cf34a6bc)): ?>
<?php $component = $__componentOriginaleda28cbc945270b2059ee861cf34a6bc; ?>
<?php unset($__componentOriginaleda28cbc945270b2059ee861cf34a6bc); ?>
<?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

         <?php $__env->slot('actions', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['label' => __('Cancel')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => '$set(\'editDrawer\', false)']); ?>
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
            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['label' => __('Save'),'spinner' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-primary','wire:click' => 'saveEdit']); ?>
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
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal40f76302556bfbc1c9486f0510e50a96)): ?>
<?php $attributes = $__attributesOriginal40f76302556bfbc1c9486f0510e50a96; ?>
<?php unset($__attributesOriginal40f76302556bfbc1c9486f0510e50a96); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal40f76302556bfbc1c9486f0510e50a96)): ?>
<?php $component = $__componentOriginal40f76302556bfbc1c9486f0510e50a96; ?>
<?php unset($__componentOriginal40f76302556bfbc1c9486f0510e50a96); ?>
<?php endif; ?>

    
    <?php if (isset($component)) { $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirm-modal','data' => ['model' => 'deleteModal','title' => __('Delete this event?'),'confirmLabel' => __('Delete'),'confirmAction' => 'delete']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirm-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['model' => 'deleteModal','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete this event?')),'confirmLabel' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Delete')),'confirmAction' => 'delete']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <p><?php echo e(__('This action is irreversible.')); ?></p>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10)): ?>
<?php $attributes = $__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10; ?>
<?php unset($__attributesOriginal2cfaf2d8c559a20e3495c081df2d0b10); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10)): ?>
<?php $component = $__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10; ?>
<?php unset($__componentOriginal2cfaf2d8c559a20e3495c081df2d0b10); ?>
<?php endif; ?>
</div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/livewire/views/752f8c30.blade.php ENDPATH**/ ?>