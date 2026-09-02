<x-app-modal wire:model="showInviteModal" :title="__('Confirm sending')" separator :open="$showInviteModal">
    <div class="space-y-4">
        <p>{{ trans_choice(
            '{1} You are about to invite :count member.|[2,*] You are about to invite :count members.',
            count($selectedMembers),
            ['count' => count($selectedMembers)],
        ) }}</p>

        <x-alert icon="o-information-circle" class="alert-info text-sm text-white">
            {{ __('They will receive an email with the tournament details and a confirmation link.') }}
        </x-alert>

        <x-textarea :label="__('Custom message (optional)')" wire:model="inviteMessage"
            :placeholder="__('E.g. Remember to bring your paddles and water bottles!')" rows="3" />

        @if ($eventPostId)
            <x-toggle :label="__('Include a link to the web event')" wire:model.live="inviteIncludeArticle"
                :hint="__('A link to the web event will be included in the email.')" />
        @endif
    </div>

    <x-slot:actions>
        <x-button :label="__('Cancel')" @click="$wire.showInviteModal = false" />
        <x-button :label="__('Send now')" icon="o-paper-airplane" class="btn-primary"
            wire:click="sendInvitations" spinner="sendInvitations" />
    </x-slot:actions>
</x-app-modal>
