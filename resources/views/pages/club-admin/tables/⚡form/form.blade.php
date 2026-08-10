<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator :title="__('Table configuration')" separator>
        <x-slot:actions>
            <x-button :label="__('Back')" icon="o-arrow-left" link="{{ url()->previous() }}" />
        </x-slot:actions>
    </x-header>

    {{-- Ajout de l'action à déclencher lors de la soumission --}}
    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Colonne Latérale --}}
            <div class="space-y-6">
                {{-- Aide contextuelle --}}
                <x-admin.shared.info-bar :description="__('Assigning a table to a room lets the room capacity be computed automatically.')">
                    <x-icon name="o-information-circle" class="w-5 h-5" />
                    <span></span>
                </x-admin.shared.info-bar>
            </div>

            {{-- Colonne Principale --}}
            <div class="lg:col-span-2 space-y-6">
                <x-card :title="__('Technical details')" shadow>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- On peut garder .live si on veut valider ou réagir en temps réel --}}
                        <x-input :label="__('Name / Identifier')" placeholder="Ex: Table 01" wire:model="name" />

                        <x-select
                            :label="__('Assign to a room')"
                            icon="o-home"
                            :placeholder="__('Choose a room')"
                            wire:model="room_id"
                            :options="$rooms" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        {{-- Remplacement des value="" par des placeholder et ajout des wire:model --}}
                        <x-input :label="__('Brand')" placeholder="Ex: Cornilleau" wire:model="brand" />
                        <x-input :label="__('Model')" placeholder="Ex: 740 Competition" wire:model="model" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        {{-- Remplacement des value="" par des placeholder et ajout des wire:model --}}
                        <x-datetime :label="__('Purchase date')" placeholder="Ex: 2023-01-01" wire:model="purchased_on" />
                    </div>
                </x-card>

                <x-card :title="__('State and Maintenance')" shadow>
                    <x-choices
                        :label="__('Current state')"
                        wire:model="state"
                        :options="$states"
                        single />
                        
                    <x-textarea 
                        :label="__('Status notes')" 
                        :placeholder="__('Report worn nets, scratches...')" 
                        wire:model="state_description" 
                        class="mt-4" />
                </x-card>
                <x-button :label="__('Save')" icon="o-check" class="btn-primary w-full" type="submit" spinner="save" />
            </div>
        </div>
    </x-form>

</div>