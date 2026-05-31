<?php echo strip_tags($header ?? ''); ?>


<?php echo strip_tags($slot); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($subcopy)): ?>

<?php echo strip_tags($subcopy); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo strip_tags($footer ?? ''); ?>

<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/vendor/mail/text/layout.blade.php ENDPATH**/ ?>