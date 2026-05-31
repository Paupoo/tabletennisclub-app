<?php if (isset($component)) { $__componentOriginal89a573612f1f1cb2dd9fc072235d4356 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal89a573612f1f1cb2dd9fc072235d4356 = $attributes; } ?>
<?php $component = Mary\View\Components\Modal::resolve(['title' => 'Confirmer l\'envoi','separator' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Modal::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'showInviteModal']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="space-y-4">
        <p><?php echo e(__('You are about to send an invitation to')); ?><strong><?php echo e(count($selectedMembers)); ?></strong>
            membres.</p>

        <?php if (isset($component)) { $__componentOriginalc667a2ae20740422749e04d86ccbd727 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc667a2ae20740422749e04d86ccbd727 = $attributes; } ?>
<?php $component = Mary\View\Components\Alert::resolve(['icon' => 'o-information-circle'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Alert::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'alert-info text-sm text-white']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            Un email contenant les détails du tournoi et un lien de confirmation leur sera envoyé.
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc667a2ae20740422749e04d86ccbd727)): ?>
<?php $attributes = $__attributesOriginalc667a2ae20740422749e04d86ccbd727; ?>
<?php unset($__attributesOriginalc667a2ae20740422749e04d86ccbd727); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc667a2ae20740422749e04d86ccbd727)): ?>
<?php $component = $__componentOriginalc667a2ae20740422749e04d86ccbd727; ?>
<?php unset($__componentOriginalc667a2ae20740422749e04d86ccbd727); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginaleda28cbc945270b2059ee861cf34a6bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleda28cbc945270b2059ee861cf34a6bc = $attributes; } ?>
<?php $component = Mary\View\Components\Textarea::resolve(['label' => __('Custom message (optional)')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Textarea::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model' => 'inviteMessage','placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('E.g. Remember to bring your paddles and water bottles!')),'rows' => '3']); ?>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventPostId): ?>
            <?php if (isset($component)) { $__componentOriginal91586e22c1998368a30f831eea05043a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91586e22c1998368a30f831eea05043a = $attributes; } ?>
<?php $component = Mary\View\Components\Toggle::resolve(['label' => __('Include a link to the web event'),'hint' => __('A link to the web event will be included in the email.')] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Toggle::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'inviteIncludeArticle']); ?>
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
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

     <?php $__env->slot('actions', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['label' => 'Annuler'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['@click' => '$wire.showInviteModal = false']); ?>
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
<?php $component = Mary\View\Components\Button::resolve(['label' => 'Envoyer maintenant','icon' => 'o-paper-airplane','spinner' => 'sendInvitations'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-primary','wire:click' => 'sendInvitations']); ?>
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
<?php if (isset($__attributesOriginal89a573612f1f1cb2dd9fc072235d4356)): ?>
<?php $attributes = $__attributesOriginal89a573612f1f1cb2dd9fc072235d4356; ?>
<?php unset($__attributesOriginal89a573612f1f1cb2dd9fc072235d4356); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal89a573612f1f1cb2dd9fc072235d4356)): ?>
<?php $component = $__componentOriginal89a573612f1f1cb2dd9fc072235d4356; ?>
<?php unset($__componentOriginal89a573612f1f1cb2dd9fc072235d4356); ?>
<?php endif; ?>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/admin/club-events/tournaments/partials/modals/invite.blade.php ENDPATH**/ ?>