<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Connexion - <?php echo e(config('app.name', 'CTT Ottignies-Blocry')); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app()->environment('production')): ?>
    <script defer src="https://stats.cttottigniesblocry.be/umami-script" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005"></script>
    <script defer src="https://stats.cttottigniesblocry.be/recorder.js" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005" data-sample-rate="0.2" data-mask-level="moderate" data-max-duration="300000"></script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</head>
<body class="font-sans antialiased">
    <!-- Background avec dégradé subtil -->
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
        
        <!-- Container principal centré -->
        <div class="flex flex-col items-center justify-center min-h-screen px-4 py-8">
            
            <!-- Logo Section avec animation subtile -->
            <div class="mb-8 flex flex-col items-center transform transition-transform duration-300 hover:scale-105">
                <a href="/" class="inline-block">
                    <?php if (isset($component)) { $__componentOriginal987d96ec78ed1cf75b349e2e5981978f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal987d96ec78ed1cf75b349e2e5981978f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.logo','data' => ['class' => 'w-24 h-24 fill-club-blue dark:fill-club-yellow transition-colors duration-300']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-24 h-24 fill-club-blue dark:fill-club-yellow transition-colors duration-300']); ?>
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
                </a>
                <!-- Nom du club sous le logo -->
                <h1 class="mt-4 text-2xl font-bold text-center text-gray-800 dark:text-gray-100">
                    CTT Ottignies-Blocry
                </h1>
                
            </div>

            <!-- Card de connexion avec design moderne -->
            <div class="w-full max-w-md">
                <div class="bg-white/80 backdrop-blur-sm dark:bg-gray-800/80 shadow-xl rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                    
                    <!-- Header avec accent de couleur -->
                    <div class="h-2 bg-gradient-to-r from-club-blue to-club-yellow"></div>
                    
                    <!-- Contenu du formulaire -->
                    <div class="px-8 py-8">
                        <?php echo e($slot); ?>

                    </div>
                    
                    <!-- Footer avec liens utiles -->
                    <div class="px-8 py-4 bg-gray-50/50 dark:bg-gray-700/50 border-t border-gray-200/50 dark:border-gray-600/50">
                        <div class="flex flex-col sm:flex-row justify-between items-center text-xs text-gray-600 dark:text-gray-400 space-y-2 sm:space-y-0">
                            <a href="/" class="hover:text-club-blue dark:hover:text-club-yellow transition-colors duration-200">
                                ← Retour au site
                            </a>
                            
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info supplémentaire -->
            <div class="mt-8 text-center">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!request()->routeIs('register')): ?>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Pas encore membre ? 
                    <a href="<?php echo e(route('register')); ?>" class="font-medium text-club-blue dark:text-club-yellow hover:underline transition-colors duration-200">
                        Rejoignez notre club !
                    </a>
                </p>
                <?php else: ?>
                <p class="mt-1 text-sm text-center text-gray-600 dark:text-gray-400">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->routeIs('login') ): ?>
                        <?php echo e(__('Connection to your member space')); ?>

                    <?php elseif(request()->routeIs('register')): ?>
                        <?php echo e(__('Note that by registering, you consent to share some private data with us.')); ?>

                        <br>
                        <?php echo e(__('We commit to never share your data with any third party, ever.')); ?>

                        <br>
                        <?php echo e(__('We commit to respect best practices to encrypt your data and keep it safe the best we can.')); ?>

                        <br>
                        <?php echo e(__('Upon request or should you leave us, we commit to delete fully your data.')); ?>

                        <br>
                        <?php echo e(__('Should we be hacked or should our policy change, we will warn you via an official email.')); ?>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <!-- Pattern décoratif subtil (optionnel) -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 rounded-full bg-club-blue/5 dark:bg-club-yellow/5"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-club-yellow/5 dark:bg-club-blue/5"></div>
        </div>
    </div>

    <style>
        /* Amélioration des focus states pour l'accessibilité */
        .focus-visible\:ring-club-blue:focus-visible {
            --tw-ring-color: theme('colors.club.blue');
        }
        
        /* Animation d'entrée subtile */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Styles pour le backdrop blur sur les navigateurs plus anciens */
        @supports not (backdrop-filter: blur(8px)) {
            .backdrop-blur-sm {
                background-color: rgba(255, 255, 255, 0.95);
            }
            .dark .backdrop-blur-sm {
                background-color: rgba(31, 41, 55, 0.95);
            }
        }
    </style>
</body>
</html><?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/layouts/login.blade.php ENDPATH**/ ?>