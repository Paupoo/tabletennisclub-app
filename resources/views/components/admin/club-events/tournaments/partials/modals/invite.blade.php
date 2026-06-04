<x-modal wire:model="showInviteModal" title="Confirmer l'envoi" separator>
    <div class="space-y-4">
        <p>{{ __('You are about to send an invitation to') }}<strong>{{ count($selectedMembers) }}</strong>
            membres.</p>

        <x-alert icon="o-information-circle" class="alert-info text-sm text-white">
            Un email contenant les détails du tournoi et un lien de confirmation leur sera envoyé.
        </x-alert>

        <x-textarea :label="__('Custom message (optional)')" wire:model="inviteMessage"
            :placeholder="__('E.g. Remember to bring your paddles and water bottles!')" rows="3" />

        @if ($eventPostId)
            <x-toggle :label="__('Include a link to the web event')" wire:model.live="inviteIncludeArticle"
                :hint="__('A link to the web event will be included in the email.')" />
        @endif
    </div>

    <x-slot:actions>
        <x-button label="Annuler" @click="$wire.showInviteModal = false" />
        <x-button label="Envoyer maintenant" icon="o-paper-airplane" class="btn-primary"
            wire:click="sendInvitations" spinner="sendInvitations" />
    </x-slot:actions>
</x-modal>
