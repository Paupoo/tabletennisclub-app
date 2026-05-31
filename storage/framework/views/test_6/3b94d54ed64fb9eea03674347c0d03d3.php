<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['url']));

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

foreach (array_filter((['url']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<tr>
<td class="header" style="padding: 24px 32px 16px; border-bottom: 1px solid #f1f5f9;">
<a href="<?php echo new \Illuminate\Support\EncodedHtmlString($url); ?>" style="display: inline-flex; align-items: center; gap: 12px; text-decoration: none;">
    <img src="<?php echo new \Illuminate\Support\EncodedHtmlString(asset('images/logo-club.svg')); ?>" alt="<?php echo new \Illuminate\Support\EncodedHtmlString(config('app.name')); ?>" style="height: 40px; width: 40px; flex-shrink: 0;">
    <span style="font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #475569; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?php echo new \Illuminate\Support\EncodedHtmlString(config('app.name')); ?></span>
</a>
</td>
</tr>
<?php /**PATH /home/aurelien/Documents/01 Projets/03-tabletennisclub-app/resources/views/vendor/mail/html/header.blade.php ENDPATH**/ ?>