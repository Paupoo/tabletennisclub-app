<nav class="<?php echo e($fixed ?? true ? 'fixed' : ''); ?> w-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-xs z-50 shadow-xs" x-data="navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <div class="shrink-0">
                    <a href="<?php echo e(route('home')); ?>">
                        <div class="flex flex-row gap-2 align-items-center">

                            <?php if (isset($component)) { $__componentOriginal987d96ec78ed1cf75b349e2e5981978f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal987d96ec78ed1cf75b349e2e5981978f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.logo','data' => ['class' => 'block w-auto text-club-blue dark:text-club-yellow fill-current h-9 group-hover:text-club-blue-light transition-colors duration-200']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'block w-auto text-club-blue dark:text-club-yellow fill-current h-9 group-hover:text-club-blue-light transition-colors duration-200']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal987d96ec78ed1cf75b349e2e5981978f)): ?>
<?php $attributes = $__attributesOriginal987d96ec78ed1cf75b349e2e5981978f; ?>
<?php unset($__attributesOriginal987d96ec78ed1cf75b349e2e5981978f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal987d96ec78ed1cf75b349e2e5981978f)): ?>
<?php $component = $__componentOriginal987d96ec78ed1cf75b349e2e5981978f; ?>
<?php unset($__componentOriginal987d96ec78ed1cf75b349e2e5981978f); ?>
<?php endif; ?>

                            <h1 class="text-2xl md:text-xl lg:text-2xl font-bold text-club-blue dark:dark:text-club-yellow">CTT Ottignies-Blocry</h1>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-4">
                    <a href="<?php echo e(route('home')); ?>" class="text-gray-900 dark:text-white hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo e(request()->routeIs('home') ? 'text-club-blue' : ''); ?>">
                        Accueil
                    </a>
                    <a href="<?php echo e(route('results')); ?>" class="text-gray-900 dark:text-white hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo e(request()->routeIs('results') ? 'text-club-blue' : ''); ?>">
                        Résultats
                    </a>
                    <a href="<?php echo e(route('eventPosts')); ?>" class="text-gray-900 dark:text-white hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo e(request()->routeIs('events') ? 'text-club-blue' : ''); ?>">
                        Événements
                    </a>
                    </a>
                    <a href="<?php echo e(route('public.clubPosts.index')); ?>" class="text-gray-900 dark:text-white hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo e(request()->routeIs('events') ? 'text-club-blue' : ''); ?>">
                        Nouvelles
                    </a>
                    <a href="<?php echo e(route('home')); ?>#contact" class="text-gray-900 dark:text-white hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <?php echo e(__('Contact')); ?>

                    </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(route('dashboard')); ?>" class="bg-club-yellow text-black px-4 py-2 rounded-md text-sm font-medium hover:bg-club-yellow-light transition-colors">
                        <?php echo e(__('My account')); ?>

                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                        <a href="<?php echo e(route('home')); ?>#join" class="bg-club-blue text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-club-blue-light transition-colors">
                        Rejoindre
                        </a>
                        <a href="<?php echo e(route('login')); ?>" class="bg-club-yellow text-black px-4 py-2 rounded-md text-sm font-medium hover:bg-club-yellow-light transition-colors">
                        <?php echo e(__('Login')); ?>

                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button @click="toggleMobileMenu()" class="text-gray-900 dark:text-white hover:text-club-blue">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div x-show="mobileMenuOpen" x-transition @click.away="closeMobileMenu()" class="md:hidden bg-white dark:bg-gray-900/95 border-t">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="<?php echo e(route('home')); ?>" @click="closeMobileMenu()" class="block text-gray-900 dark:text-white hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-base font-medium">Accueil</a>
            <a href="<?php echo e(route('results')); ?>" @click="closeMobileMenu()" class="block text-gray-900 dark:text-white hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-base font-medium"><?php echo e(__('Results')); ?></a>
            <a href="<?php echo e(route('eventPosts')); ?>" @click="closeMobileMenu()" class="block text-gray-900 dark:text-white hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-base font-medium"><?php echo e(__('Events')); ?></a>
            <a href="<?php echo e(route('public.clubPosts.index')); ?>" @click="closeMobileMenu()" class="block text-gray-900 dark:text-white hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-base font-medium">Nouvelles</a>
            <a href="<?php echo e(route('home')); ?>#contact" @click="closeMobileMenu()" class="block text-gray-900 dark:text-white hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-base font-medium">Contact</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                <a href="<?php echo e(route('home')); ?>#join" @click="closeMobileMenu()" class="block bg-club-blue text-white px-3 py-2 rounded-md text-base font-medium">Rejoindre</a>
                <a href="<?php echo e(route('login')); ?>" @click="closeMobileMenu()" class="block bg-club-yellow text-black px-3 py-2 rounded-md text-base font-medium"><?php echo e(__('Login')); ?></a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <a href="<?php echo e(route('dashboard')); ?>" @click="closeMobileMenu()" class="block bg-club-yellow text-black px-3 py-2 rounded-md text-base font-medium"><?php echo e(__('My Account')); ?></a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</nav>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/components/public/navigation.blade.php ENDPATH**/ ?>