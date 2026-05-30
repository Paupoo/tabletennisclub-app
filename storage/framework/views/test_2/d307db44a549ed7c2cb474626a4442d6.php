<?php
use App\Models\ClubAdmin\Users\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;
?>

<div class="col-span-6 grid gap-2 md:col-span-4">
    <div class="grid gap-6 lg:grid-cols-2">
        <?php if (isset($component)) { $__componentOriginal39f5f583c267508d0f78e9f3c01a4a63 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal39f5f583c267508d0f78e9f3c01a4a63 = $attributes; } ?>
<?php $component = Mary\View\Components\Group::resolve(['options' => $theme_options,'label' => 'Theme'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('group'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Group::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-soft','wire:model.live' => 'theme_choice']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal39f5f583c267508d0f78e9f3c01a4a63)): ?>
<?php $attributes = $__attributesOriginal39f5f583c267508d0f78e9f3c01a4a63; ?>
<?php unset($__attributesOriginal39f5f583c267508d0f78e9f3c01a4a63); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal39f5f583c267508d0f78e9f3c01a4a63)): ?>
<?php $component = $__componentOriginal39f5f583c267508d0f78e9f3c01a4a63; ?>
<?php unset($__componentOriginal39f5f583c267508d0f78e9f3c01a4a63); ?>
<?php endif; ?>
    </div>
</div><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/storage/framework/views/test_2/livewire/views/7bf272c1.blade.php ENDPATH**/ ?>