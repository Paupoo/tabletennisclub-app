<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Bar sales')"
        :subtitle="__('What sells, what sleeps: a guide for the next purchases.')" />
</div>
