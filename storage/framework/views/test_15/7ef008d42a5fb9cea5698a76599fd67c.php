<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Événements - Ace Table Tennis Club']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>


    <!-- Header -->
    <div class="relative h-auto pt-16 text-white flex items-center overflow-hidden">
        <!-- Image de fond -->
        <div class="absolute inset-0">
            <img src="<?php echo e(asset('images/background_events.webp')); ?>" alt="Tennis table background" class="w-full h-full object-cover">
            <!-- Overlay avec votre dégradé + opacité -->
            <div class="absolute inset-0 bg-gradient-to-br from-club-blue/85 via-club-blue/80 to-club-blue-light/85"></div>
        </div>

        <!-- Contenu -->
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 drop-shadow-lg"><?php echo e(__('Upcoming events')); ?></h1>
            <p class="text-xl opacity-90 drop-shadow-md"><?php echo e(__('Join us for tournaments, training sessions and community events')); ?></p>
        </div>
    </div>

    <!-- EventPost Filters -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ selectedCategory: 'all' }">
        <div class="flex flex-wrap gap-2 mb-8">
            <button @click="selectedCategory = 'all'"
                    :class="selectedCategory === 'all' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Tous les Événements
            </button>
            <button @click="selectedCategory = 'tournament'"
                    :class="selectedCategory === 'tournament' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Tournois
            </button>
            <button @click="selectedCategory = 'training'"
                    :class="selectedCategory === 'training' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Entraînement
            </button>
            <button @click="selectedCategory = 'club-life'"
                    :class="selectedCategory === 'club-life' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Vie du club
            </button>
        </div>

        <!-- Events Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $events ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.event-card','data' => ['event' => $event]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.event-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['event' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $attributes = $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $component = $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <!-- Default events if no data provided -->
                <?php if (isset($component)) { $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.event-card','data' => ['event' => [
                    'category' => 'tournament',
                    'title' => 'Championnat du Nouvel An',
                    'description' => 'Championnat annuel du club ouvert à tous les membres. Catégories simple et double disponibles.',
                    'date' => '15 Janvier 2025',
                    'time' => '9h00 - 18h00',
                    'location' => 'Salle Principale',
                    'price' => '25€ d\'inscription',
                    'icon' => '🏆'
                ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.event-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['event' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
                    'category' => 'tournament',
                    'title' => 'Championnat du Nouvel An',
                    'description' => 'Championnat annuel du club ouvert à tous les membres. Catégories simple et double disponibles.',
                    'date' => '15 Janvier 2025',
                    'time' => '9h00 - 18h00',
                    'location' => 'Salle Principale',
                    'price' => '25€ d\'inscription',
                    'icon' => '🏆'
                ])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $attributes = $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $component = $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>

                <?php if (isset($component)) { $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.event-card','data' => ['event' => [
                    'category' => 'training',
                    'title' => 'Atelier Techniques Avancées',
                    'description' => 'Maîtrisez les services avancés, les effets et le jeu tactique avec notre entraîneur professionnel.',
                    'date' => 'Tous les samedis',
                    'time' => '14h00 - 16h00',
                    'location' => 'Salle d\'Entraînement A',
                    'price' => 'Max 8 participants',
                    'icon' => '🎯'
                ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.event-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['event' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
                    'category' => 'training',
                    'title' => 'Atelier Techniques Avancées',
                    'description' => 'Maîtrisez les services avancés, les effets et le jeu tactique avec notre entraîneur professionnel.',
                    'date' => 'Tous les samedis',
                    'time' => '14h00 - 16h00',
                    'location' => 'Salle d\'Entraînement A',
                    'price' => 'Max 8 participants',
                    'icon' => '🎯'
                ])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $attributes = $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $component = $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>

                <?php if (isset($component)) { $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.event-card','data' => ['event' => [
                    'category' => 'club-life',
                    'title' => 'Soirée Sociale Mensuelle',
                    'description' => 'Jeux décontractés, pizza et amusement ! Parfait pour rencontrer d\'autres membres et se détendre.',
                    'date' => 'Premier vendredi de chaque mois',
                    'time' => '19h00 - 22h00',
                    'location' => 'Salon du Club',
                    'price' => 'Nourriture et boissons incluses',
                    'icon' => '🎉'
                ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.event-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['event' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
                    'category' => 'club-life',
                    'title' => 'Soirée Sociale Mensuelle',
                    'description' => 'Jeux décontractés, pizza et amusement ! Parfait pour rencontrer d\'autres membres et se détendre.',
                    'date' => 'Premier vendredi de chaque mois',
                    'time' => '19h00 - 22h00',
                    'location' => 'Salon du Club',
                    'price' => 'Nourriture et boissons incluses',
                    'icon' => '🎉'
                ])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $attributes = $__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__attributesOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1)): ?>
<?php $component = $__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1; ?>
<?php unset($__componentOriginal6f92b674c0c39aabb80057eba8d4e7d1); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <!-- Call to Action -->
        <div class="bg-club-blue rounded-lg p-8 text-white text-center">
            <h2 class="text-3xl font-bold mb-4">Ne Ratez Rien !</h2>
            <p class="text-xl mb-6 opacity-90">
                Rejoignez nos événements et devenez membre de la communauté Ace TTC. Tous les niveaux sont les bienvenus !
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?php echo e(route('home')); ?>#join" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                    Devenir Membre
                </a>
                <a href="<?php echo e(route('home')); ?>#contact" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                    Nous Contacter
                </a>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/public/events.blade.php ENDPATH**/ ?>