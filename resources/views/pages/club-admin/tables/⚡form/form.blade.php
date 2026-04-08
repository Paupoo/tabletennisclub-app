<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="Configuration de la Table" separator>
        <x-slot:actions>
            <x-button label="Retour" icon="o-arrow-left" link="{{ url()->previous() }}" />
        </x-slot:actions>
    </x-header>

    {{-- Ajout de l'action à déclencher lors de la soumission --}}
    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Colonne Latérale --}}
            <div class="space-y-6">
                <x-admin.shared.side-card title="Statut" shadow>
                    <div class="space-y-4">
                        <x-toggle label="Disponible à la réservation" wire:model="is_available" />
                        {{-- Logique inversée pour correspondre au booléen en base --}}
                        <x-toggle label="Prête pour la compétition" wire:model="is_competition_ready" />
                    </div>
                </x-admin.shared.side-card>

                {{-- Aide contextuelle --}}
                <x-admin.shared.info-bar :description="__('L\'assignation à une salle permet de calculer automatiquement la capacité totale de celle-ci.')">
                    <x-icon name="o-information-circle" class="w-5 h-5" />
                    <span></span>
                </x-admin.shared.info-bar>
            </div>

            {{-- Colonne Principale --}}
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Détails techniques" shadow>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- On peut garder .live si on veut valider ou réagir en temps réel --}}
                        <x-input label="Nom / Identifiant" placeholder="Ex: Table 01" wire:model="name" />

                        <x-select
                            label="Assigner à une salle"
                            icon="o-home"
                            placeholder="Choisir une salle..."
                            wire:model="room_id"
                            :options="$rooms" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        {{-- Remplacement des value="" par des placeholder et ajout des wire:model --}}
                        <x-input label="Marque" placeholder="Ex: Cornilleau" wire:model="brand" />
                        <x-input label="Modèle" placeholder="Ex: 740 Competition" wire:model="model" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        {{-- Remplacement des value="" par des placeholder et ajout des wire:model --}}
                        <x-datetime label="Date d'achat" placeholder="Ex: 2023-01-01" wire:model="purchased_on" />
                    </div>
                </x-card>

                <x-card title="État et Maintenance" shadow>
                    <x-choices
                        label="État actuel"
                        wire:model="state"
                        :options="$states"
                        single />
                        
                    <x-textarea 
                        label="Notes sur l'état" 
                        placeholder="Signalement de filets usés, rayures..." 
                        wire:model="state_description" 
                        class="mt-4" />
                </x-card>
                <x-button label="Enregistrer" icon="o-check" class="btn-primary w-full" type="submit" spinner="save" />
            </div>
        </div>
    </x-form>

</div>