<x-modal wire:model="showInviteModal" title="Confirmer l'envoi" separator>
    <div class="space-y-4">
        <p>Vous êtes sur le point d'envoyer une invitation à <strong>{{ count($selectedMembers) }}</strong>
            membres.</p>

        <x-alert icon="o-information-circle" class="alert-info text-sm text-white">
            Un email contenant les détails du tournoi et un lien de confirmation leur sera envoyé.
        </x-alert>

        <x-textarea label="Message personnalisé (optionnel)" wire:model="inviteMessage"
            placeholder="Ex: Pensez à prendre vos raquettes et vos gourdes !" rows="3" />
    </div>

    <x-slot:actions>
        <x-button label="Annuler" @click="$wire.showInviteModal = false" />
        <x-button label="Envoyer maintenant" icon="o-paper-airplane" class="btn-primary"
            wire:click="sendInvitations" spinner="sendInvitations" />
    </x-slot:actions>
</x-modal>