<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Accueil - CTT Ottignies']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <section id="home">
        <?php if (isset($component)) { $__componentOriginal3c3f39e5cda7b00c1f44033bfe927316 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3c3f39e5cda7b00c1f44033bfe927316 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.hero','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.hero'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3c3f39e5cda7b00c1f44033bfe927316)): ?>
<?php $attributes = $__attributesOriginal3c3f39e5cda7b00c1f44033bfe927316; ?>
<?php unset($__attributesOriginal3c3f39e5cda7b00c1f44033bfe927316); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3c3f39e5cda7b00c1f44033bfe927316)): ?>
<?php $component = $__componentOriginal3c3f39e5cda7b00c1f44033bfe927316; ?>
<?php unset($__componentOriginal3c3f39e5cda7b00c1f44033bfe927316); ?>
<?php endif; ?>
    </section>
    
    <section id="about">
        <?php if (isset($component)) { $__componentOriginal602f2010ac49309a38b04a9ccb204124 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal602f2010ac49309a38b04a9ccb204124 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.about-section','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.about-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal602f2010ac49309a38b04a9ccb204124)): ?>
<?php $attributes = $__attributesOriginal602f2010ac49309a38b04a9ccb204124; ?>
<?php unset($__attributesOriginal602f2010ac49309a38b04a9ccb204124); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal602f2010ac49309a38b04a9ccb204124)): ?>
<?php $component = $__componentOriginal602f2010ac49309a38b04a9ccb204124; ?>
<?php unset($__componentOriginal602f2010ac49309a38b04a9ccb204124); ?>
<?php endif; ?>
    </section>
    
    <section id="join">
        <?php if (isset($component)) { $__componentOriginal587fff057a463a96691be150a4e51c65 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal587fff057a463a96691be150a4e51c65 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.join-section','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.join-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal587fff057a463a96691be150a4e51c65)): ?>
<?php $attributes = $__attributesOriginal587fff057a463a96691be150a4e51c65; ?>
<?php unset($__attributesOriginal587fff057a463a96691be150a4e51c65); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal587fff057a463a96691be150a4e51c65)): ?>
<?php $component = $__componentOriginal587fff057a463a96691be150a4e51c65; ?>
<?php unset($__componentOriginal587fff057a463a96691be150a4e51c65); ?>
<?php endif; ?>
    </section>

    <section id="news">
        <?php if (isset($component)) { $__componentOriginal26486fe76972a80513ff8284844341c8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal26486fe76972a80513ff8284844341c8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.news-section','data' => ['articles' => $articles ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.news-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['articles' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($articles ?? [])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal26486fe76972a80513ff8284844341c8)): ?>
<?php $attributes = $__attributesOriginal26486fe76972a80513ff8284844341c8; ?>
<?php unset($__attributesOriginal26486fe76972a80513ff8284844341c8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal26486fe76972a80513ff8284844341c8)): ?>
<?php $component = $__componentOriginal26486fe76972a80513ff8284844341c8; ?>
<?php unset($__componentOriginal26486fe76972a80513ff8284844341c8); ?>
<?php endif; ?>
    </section>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($featuredEvents ?? collect())->isNotEmpty()): ?>
        <section id="events">
            <?php if (isset($component)) { $__componentOriginal207202ae0ea7755c811a7fce072f9ec9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal207202ae0ea7755c811a7fce072f9ec9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.featured-events-section','data' => ['events' => $featuredEvents]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.featured-events-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['events' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($featuredEvents)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal207202ae0ea7755c811a7fce072f9ec9)): ?>
<?php $attributes = $__attributesOriginal207202ae0ea7755c811a7fce072f9ec9; ?>
<?php unset($__attributesOriginal207202ae0ea7755c811a7fce072f9ec9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal207202ae0ea7755c811a7fce072f9ec9)): ?>
<?php $component = $__componentOriginal207202ae0ea7755c811a7fce072f9ec9; ?>
<?php unset($__componentOriginal207202ae0ea7755c811a7fce072f9ec9); ?>
<?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <section id="schedules">
        <?php if (isset($component)) { $__componentOriginal02682e77407eefe828573389f563dc73 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal02682e77407eefe828573389f563dc73 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.schedule-section','data' => ['schedules' => $schedules ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.schedule-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['schedules' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($schedules ?? [])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal02682e77407eefe828573389f563dc73)): ?>
<?php $attributes = $__attributesOriginal02682e77407eefe828573389f563dc73; ?>
<?php unset($__attributesOriginal02682e77407eefe828573389f563dc73); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal02682e77407eefe828573389f563dc73)): ?>
<?php $component = $__componentOriginal02682e77407eefe828573389f563dc73; ?>
<?php unset($__componentOriginal02682e77407eefe828573389f563dc73); ?>
<?php endif; ?>
    </section>
    
    <section id="contact">
        <?php if (isset($component)) { $__componentOriginal4f148cf80961598c35eb8116be8b24cb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f148cf80961598c35eb8116be8b24cb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.contact-section','data' => ['club' => $club]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.contact-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['club' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($club)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f148cf80961598c35eb8116be8b24cb)): ?>
<?php $attributes = $__attributesOriginal4f148cf80961598c35eb8116be8b24cb; ?>
<?php unset($__attributesOriginal4f148cf80961598c35eb8116be8b24cb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f148cf80961598c35eb8116be8b24cb)): ?>
<?php $component = $__componentOriginal4f148cf80961598c35eb8116be8b24cb; ?>
<?php unset($__componentOriginal4f148cf80961598c35eb8116be8b24cb); ?>
<?php endif; ?>
    </section>
    
    <section id="sponsors">
        <?php if (isset($component)) { $__componentOriginalee6fc1477dd07fc7586008bf4e147cea = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee6fc1477dd07fc7586008bf4e147cea = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.public.sponsors-section','data' => ['sponsors' => $sponsors ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public.sponsors-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['sponsors' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sponsors ?? [])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee6fc1477dd07fc7586008bf4e147cea)): ?>
<?php $attributes = $__attributesOriginalee6fc1477dd07fc7586008bf4e147cea; ?>
<?php unset($__attributesOriginalee6fc1477dd07fc7586008bf4e147cea); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee6fc1477dd07fc7586008bf4e147cea)): ?>
<?php $component = $__componentOriginalee6fc1477dd07fc7586008bf4e147cea; ?>
<?php unset($__componentOriginalee6fc1477dd07fc7586008bf4e147cea); ?>
<?php endif; ?>
    </section>
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
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/public/home.blade.php ENDPATH**/ ?>