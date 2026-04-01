{{-- Drawer pour les actions de masse --}}
<x-drawer wire:model="bulkDrawer" title="{{ __('Bulk actions') }}" right separator with-close-button class="w-1/3">
    <div class="mb-4">
        <x-badge :value="count($selectedPeople) . ' ' . __('selected players')" class="badge-primary" />
    </div>

    <div class="grid gap-3">
        <x-button
            label="{{ __('Confirm presences') }}"
            icon="o-check"
            class="btn-ghost justify-start"
            wire:click="confirmBulkPresence"
            spinner />

        <x-button
            label="{{ __('Mark absent (No-show)') }}"
            icon="o-no-symbol"
            class="btn-ghost btn-warning justify-start"
            wire:click="confirmBulkNoShow"
            spinner />

        <hr class="my-2" />

        <x-button
            label="{{ __('Cancel registrations') }}"
            icon="o-trash"
            class="btn-outline btn-error justify-start"
            wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ces inscriptions ?') }}"
            wire:click="confirmBulkCancel"
            spinner />
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Annuler') }}" @click="$wire.bulkDrawer = false" />
    </x-slot:actions>
</x-drawer>