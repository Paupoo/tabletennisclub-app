<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['article', 'index' => 0]));

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

foreach (array_filter((['article', 'index' => 0]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<article class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-club-blue hover:shadow-lg transition-all duration-300 group"
         style="transition-delay: <?php echo e($index * 0.1); ?>s;">
    <div class="aspect-video bg-gray-100 overflow-hidden">
        <img src="<?php echo e(Storage::url($article->image)); ?>" alt="<?php echo e($article['title']); ?>"
             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
    </div>
    <div class="p-6">
        <div class="flex items-center justify-between mb-3">
            <span class="<?php if($article['category'] === 'Compétition'): ?> bg-club-blue text-white <?php elseif($article['category'] === 'Formation'): ?> bg-club-yellow text-club-blue <?php else: ?> bg-gray-100 text-gray-800 <?php endif; ?> text-xs font-medium px-3 py-1 rounded-full">
                <?php echo e($article['category']); ?>

            </span>
            <time class="text-sm text-gray-500"><?php echo e($article['date']); ?></time>
        </div>

        <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-club-blue transition-colors line-clamp-2">
            <a href="<?php echo e(route('public.clubPosts.show', $article['slug'])); ?>">
                <?php echo e($article['title']); ?>

            </a>
        </h3>

        <p class="text-gray-600 mb-4 line-clamp-3">
            <?php echo e($article['excerpt']); ?>

        </p>

        <div class="flex items-center justify-between">
            <a href="<?php echo e(route('public.clubPosts.show', $article['slug'])); ?>"
               class="text-club-blue hover:text-club-blue-light font-semibold text-sm inline-flex items-center">
                Lire la suite
                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($article['reading_time'])): ?>
                <span class="text-xs text-gray-500"><?php echo e($article['reading_time']); ?> min</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</article>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/news-card-full.blade.php ENDPATH**/ ?>