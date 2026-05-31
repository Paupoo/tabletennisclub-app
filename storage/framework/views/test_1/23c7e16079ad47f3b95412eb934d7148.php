<section class="py-20 bg-white border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 animate-on-scroll">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Nos Sponsors</h2>
            <p class="text-lg text-gray-600">
                Merci à nos incroyables sponsors qui rendent notre club possible
            </p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center animate-on-scroll">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $sponsors ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sponsor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="bg-gray-800 rounded-lg p-6 text-center h-44 flex items-center justify-center">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sponsor['url']): ?>
                    <a href="<?php echo e($sponsor['url']); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sponsor['logo']): ?>
                            <img src="<?php echo e($sponsor['logo']); ?>" alt="<?php echo e($sponsor['name']); ?>" class="max-h-40 max-w-full rounded-xl">
                        <?php else: ?>
                            <span class="text-gray-400 font-medium"><?php echo e($sponsor['name']); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sponsor['url']): ?>
                    </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last): ?>
                <div class="bg-gray-800 rounded-lg p-6 text-center h-44 flex items-center justify-center">
                    <a href="#contact" target="_self" rel="noopener noreferrer" class="flex items-center justify-center">
                            <span class="text-gray-400 font-medium"><?php echo e(__('Your company here?')); ?></span>
                    </a>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <!-- Placeholder sponsor logos -->
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
                <div class="bg-gray-100 rounded-lg p-6 text-center h-24 flex items-center justify-center">
                    <span class="text-gray-400 font-medium">Logo Sponsor</span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        
        <div class="text-center mt-8 animate-on-scroll">
            <p class="text-gray-600 mb-4"><?php echo e(__('Interested in sponsoring our club?')); ?></p>
            <a href="#contact" target="_self" class="text-club-blue hover:text-club-blue-light font-semibold"><?php echo e(__('Contact us for partnership opportunities')); ?></a>
        </div>
    </div>
</section>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/sponsors-section.blade.php ENDPATH**/ ?>