<?php if (isset($component)) { $__componentOriginalaa758e6a82983efcbf593f765e026bd9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa758e6a82983efcbf593f765e026bd9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => $__env->getContainer()->make(Illuminate\View\Factory::class)->make('mail::message'),'data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mail::message'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

# <?php echo new \Illuminate\Support\EncodedHtmlString(config('app.name')); ?>

*Votre club de sport*

---

## <?php echo new \Illuminate\Support\EncodedHtmlString(__('welcome-email.title', ['name' => $contact->first_name])); ?>


<?php echo new \Illuminate\Support\EncodedHtmlString(__('welcome-email.paragraph1')); ?>


<?php echo new \Illuminate\Support\EncodedHtmlString(__('welcome-email.paragraph2')); ?>

---

### 📋 Votre demande en bref :

- **Centre d'intérêt :** <?php echo new \Illuminate\Support\EncodedHtmlString($contact->interest->getLabel() ?: 'Non spécifié'); ?>

- **Date de demande :** <?php echo new \Illuminate\Support\EncodedHtmlString($contact->created_at->format('d/m/Y')); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contact->phone): ?>
- **Téléphone :** <?php echo new \Illuminate\Support\EncodedHtmlString($contact->phone); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

### Pourquoi choisir notre club ?

- 🏆 Une équipe d'entraîneurs qualifiés et passionnés
- 🤝 Un environnement convivial et familial
- 📈 Des programmes adaptés à tous les niveaux
- 🏅 Des compétitions régulières pour progresser
- 🎯 Des installations modernes et bien équipées

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contact->message): ?>
---

### Votre message :

> *"<?php echo new \Illuminate\Support\EncodedHtmlString($contact->message); ?>"*

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

### Nous contacter :

N'hésitez pas à nous contacter si vous avez des questions. Nous sommes là pour vous accompagner dans votre projet sportif !
Une erreur, une précision à ajouter ? Pas problème, répondez simplement à cet email pour préciser votre demande.

---

### 📞 Nos coordonnées :

- **📧 Email :** <?php echo new \Illuminate\Support\EncodedHtmlString(config('mail.from.address')); ?>

- **📞 Téléphone :** <?php echo new \Illuminate\Support\EncodedHtmlString(config('app.club_phone_number')); ?> - (lu.-ven. 16h-20h).
- **📍 Adresse :** <?php echo new \Illuminate\Support\EncodedHtmlString(config('app.club_street') . ', ' . config('app.club_zip_code') . ' ' . config('app.club_city')); ?>

- **🌐 Site web :** <?php echo new \Illuminate\Support\EncodedHtmlString(config('app.url')); ?>


---

Sportivement,
**L'équipe de <?php echo new \Illuminate\Support\EncodedHtmlString(config('app.name')); ?>**

---

*Cet email a été envoyé automatiquement suite à votre demande de contact.*
*Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce message.*
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa758e6a82983efcbf593f765e026bd9)): ?>
<?php $attributes = $__attributesOriginalaa758e6a82983efcbf593f765e026bd9; ?>
<?php unset($__attributesOriginalaa758e6a82983efcbf593f765e026bd9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa758e6a82983efcbf593f765e026bd9)): ?>
<?php $component = $__componentOriginalaa758e6a82983efcbf593f765e026bd9; ?>
<?php unset($__componentOriginalaa758e6a82983efcbf593f765e026bd9); ?>
<?php endif; ?>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/mail/contact-form-mail-confirmation.blade.php ENDPATH**/ ?>