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

<?php
    $colorMap = [
        'Hommes'   => ['bg' => 'bg-blue-50  dark:bg-blue-900/40',  'text' => 'text-blue-700  dark:text-blue-300',  'dot' => 'bg-blue-100  dark:bg-blue-800/60'],
        'Vétérans' => ['bg' => 'bg-amber-50 dark:bg-amber-900/40', 'text' => 'text-amber-700 dark:text-amber-300', 'dot' => 'bg-amber-100 dark:bg-amber-800/60'],
        'Dames'    => ['bg' => 'bg-pink-50  dark:bg-pink-900/40',  'text' => 'text-pink-700  dark:text-pink-300',  'dot' => 'bg-pink-100  dark:bg-pink-800/60'],
    ];

    $c = $colorMap[$team->category] ?? [
        'bg'   => 'bg-base-200/60',
        'text' => 'text-base-content/70',
        'dot'  => 'bg-base-300',
    ];

    $hasCapitain = $team->captain_name !== '—';
    $showRoute   = route('admin.interclubs.teams.show', $team->id);
    $editRoute   = route('admin.interclubs.teams.edit', $team->id);
?>

<div class="flex flex-col overflow-hidden rounded-xl border border-base-200 bg-base-100">

    
    <div class="flex items-center gap-3 <?php echo e($c['bg']); ?> px-4 py-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?php echo e($c['dot']); ?>">
            <span class="<?php echo e($c['text']); ?> text-xl font-bold leading-none"><?php echo e($team->teamLetter); ?></span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="<?php echo e($c['text']); ?> truncate text-sm font-semibold"><?php echo e($team->name); ?></p>
            <p class="<?php echo e($c['text']); ?> mt-0.5 truncate text-[11px] opacity-70"><?php echo e($team->division); ?></p>
        </div>
    </div>

    
    <div class="flex-1 divide-y divide-base-200 px-4">

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-[11px] font-medium uppercase tracking-wide text-base-content/40">Capitaine</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasCapitain): ?>
                <span class="truncate text-sm font-medium text-base-content"><?php echo e($team->captain_name); ?></span>
            <?php else: ?>
                <span class="text-xs italic text-base-content/25"><?php echo e(__('Not defined')); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-[11px] font-medium uppercase tracking-wide text-base-content/40">Noyau</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->membersCount > 0): ?>
                <span class="text-sm font-medium text-base-content">
                    <?php echo e($team->membersCount); ?> joueur<?php echo e($team->membersCount > 1 ? 's' : ''); ?>

                </span>
            <?php else: ?>
                <span class="text-xs italic text-base-content/25">Aucun joueur</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="flex items-center justify-between gap-4 py-2.5">
            <span class="shrink-0 text-[11px] font-medium uppercase tracking-wide text-base-content/40">Prochain match</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->nextMatchDate): ?>
                <span class="text-sm font-medium text-base-content">
                    <?php echo e(\Carbon\Carbon::parse($team->nextMatchDate)->translatedFormat('D d M')); ?>

                </span>
            <?php else: ?>
                <span class="text-xs italic text-base-content/25">—</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

    </div>

    
    <div class="flex items-center justify-between border-t border-base-200 bg-base-200/40 px-3 py-2">
        <div class="flex gap-0.5">
            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-eye','label' => __('Details'),'link' => ''.e($showRoute).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-xs text-base-content/50 hover:text-base-content']); ?>
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
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->is_admin || auth()->user()->is_committee_member): ?>
                <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-pencil','label' => 'Modifier','link' => ''.e($editRoute).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-xs text-base-content/50 hover:text-base-content']); ?>
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
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->is_admin || auth()->user()->is_committee_member): ?>
            <?php if (isset($component)) { $__componentOriginal602b228a887fab12f0012a3179e5b533 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602b228a887fab12f0012a3179e5b533 = $attributes; } ?>
<?php $component = Mary\View\Components\Button::resolve(['icon' => 'o-trash'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Button::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'btn-ghost btn-xs text-error hover:opacity-80','wire:click' => 'confirmDelete('.e($team->id).')']); ?>
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
    </div>

</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/admin/club-events/teams/team-card.blade.php ENDPATH**/ ?>