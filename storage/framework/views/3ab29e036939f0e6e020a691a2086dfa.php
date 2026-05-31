<?php
    $rankings  = $this->rankings;
    $isDoubles = $tournament->match_type === 'double';
    $top3      = $rankings->take(3)->keyBy('rank');
    $rest      = $rankings->skip(3);

    $podiumOrder    = [2, 1, 3];
    $podiumHeight   = [2 => 'h-16', 1 => 'h-24', 3 => 'h-10'];
    $podiumLabel    = [1 => __('Champion'), 2 => __('Runner-up'), 3 => __('3rd place')];
    $podiumRing     = [
        1 => 'ring-2 ring-amber-400 ring-offset-2 ring-offset-base-100',
        2 => 'ring-2 ring-slate-400 ring-offset-2 ring-offset-base-100',
        3 => 'ring-2 ring-orange-400 ring-offset-2 ring-offset-base-100',
    ];
    $podiumPlatform = [
        1 => 'bg-amber-400/20 border-t-2 border-amber-400/40',
        2 => 'bg-slate-400/10 border-t-2 border-slate-400/30',
        3 => 'bg-orange-400/10 border-t-2 border-orange-400/30',
    ];
    $podiumNumber   = [
        1 => 'text-amber-400',
        2 => 'text-slate-400',
        3 => 'text-orange-400',
    ];

    $entryName = function (array $entry) use ($isDoubles): string {
        if ($isDoubles && isset($entry['pair'])) {
            return $entry['pair']->displayName();
        }
        return $entry['user']->full_name ?? '—';
    };

    $entryInitials = function (array $entry) use ($isDoubles): string {
        if ($isDoubles && isset($entry['pair'])) {
            $p1 = mb_strtoupper(mb_substr($entry['pair']->player1->last_name ?? '?', 0, 1));
            $p2 = mb_strtoupper(mb_substr($entry['pair']->player2->last_name ?? '?', 0, 1));
            return "{$p1}/{$p2}";
        }
        $f = mb_strtoupper(mb_substr($entry['user']->first_name ?? '?', 0, 1));
        $l = mb_strtoupper(mb_substr($entry['user']->last_name ?? '', 0, 1));
        return "{$f}{$l}";
    };
?>

<div <?php if($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING): ?> wire:poll.5s <?php endif; ?> class="mt-6">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankings->isEmpty()): ?>
        <div class="flex flex-col items-center py-20 opacity-30">
            <?php if (isset($component)) { $__componentOriginalce0070e6ae017cca68172d0230e44821 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce0070e6ae017cca68172d0230e44821 = $attributes; } ?>
<?php $component = Mary\View\Components\Icon::resolve(['name' => 'o-chart-bar'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Mary\View\Components\Icon::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-12 h-12 mb-3']); ?>
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
            <p class="text-sm"><?php echo e(__('Rankings will appear as matches are completed.')); ?></p>
        </div>

    <?php else: ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($top3->count() >= 2): ?>
            <div class="flex items-end justify-center gap-4 mb-8 px-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $podiumOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($entry = $top3->get($rank)): ?>
                        <div class="flex flex-col items-center flex-1 min-w-0">

                            
                            <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['text-3xl font-black opacity-20 leading-none mb-1', $podiumNumber[$rank]]); ?>">
                                <?php echo e($rank); ?>

                            </span>

                            
                            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['w-12 h-12 rounded-full flex items-center justify-center bg-base-200 font-black text-sm mb-2', $podiumRing[$rank]]); ?>">
                                <?php echo e($entryInitials($entry)); ?>

                            </div>

                            
                            <p class="text-xs font-bold text-center leading-tight truncate w-full">
                                <?php echo e($entryName($entry)); ?>

                            </p>

                            
                            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['w-full rounded-t-md mt-2 flex items-center justify-center', $podiumHeight[$rank], $podiumPlatform[$rank]]); ?>">
                                <span class="text-[10px] font-bold uppercase tracking-wider opacity-50">
                                    <?php echo e($podiumLabel[$rank]); ?>

                                </span>
                            </div>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="space-y-1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rankings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $rank = $entry['rank']; ?>
                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'ranking-'.e($isDoubles && isset($entry['pair']) ? 'pair-'.$entry['pair']->id : ($entry['user']->id ?? $rank)).''; ?>wire:key="ranking-<?php echo e($isDoubles && isset($entry['pair']) ? 'pair-'.$entry['pair']->id : ($entry['user']->id ?? $rank)); ?>"
                    class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg',
                        'bg-amber-400/10'  => $rank === 1,
                        'bg-slate-400/10'  => $rank === 2,
                        'bg-orange-400/10' => $rank === 3,
                        'hover:bg-base-200/50 transition-colors' => $rank > 3,
                    ]); ?>">

                    
                    <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'w-6 text-center text-xs font-mono font-black shrink-0',
                        'text-amber-500'  => $rank === 1,
                        'text-slate-400'  => $rank === 2,
                        'text-orange-400' => $rank === 3,
                        'opacity-30'      => $rank > 3,
                    ]); ?>"><?php echo e($rank); ?></span>

                    
                    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 bg-base-200',
                        'ring-1 ring-amber-400'  => $rank === 1,
                        'ring-1 ring-slate-400'  => $rank === 2,
                        'ring-1 ring-orange-400' => $rank === 3,
                    ]); ?>">
                        <?php echo e($entryInitials($entry)); ?>

                    </div>

                    
                    <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'flex-1 text-sm font-semibold truncate',
                        'font-bold' => $rank <= 3,
                    ]); ?>"><?php echo e($entryName($entry)); ?></span>

                    
                    <span class="text-xs opacity-40 shrink-0"><?php echo e($entry['result']); ?></span>

                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/admin/club-events/tournaments/partials/live/tabs/rankings.blade.php ENDPATH**/ ?>