<x-tab name="3" label="{{ __('Registrations') }}" icon="o-users">
    <div class="mt-8 space-y-6 animate-in fade-in duration-500">

        <x-card title="{{ __('Registrated people') }}" shadow>
            <x-slot:menu>
                <div class="flex gap-2">
                    <x-button :label="!$this->registrationClosed
                                    ? __('Close Registrations')
                                    : __('Open Registrations')" :icon="!$this->registrationClosed ? 'o-lock-closed' : 'o-lock-open'" class="btn-primary btn-sm"
                        wire:click="toggleRegistrations" />
                    <x-button
                        label="Bulk actions"
                        icon="o-funnel"
                        class="btn-ghost btn-sm"
                        @click="$wire.bulkDrawer = true"
                        ::class="{ 'btn-disabled opacity-50': $wire.selectedPeople.length === 0 }" />

                </div>
            </x-slot:menu>
            <x-tabs selected="reg-list">
                <x-tab name="reg-list" label="Liste principale" icon="o-identification">
                    <x-table wire:model.live.debounce.500ms="selectedPeople" :headers="[
                                    ['key' => 'name', 'label' => 'Joueur'],
                                    ['key' => 'ranking', 'label' => 'Classement'],
                                ]" :rows="$registrated" selectable>
                        @scope('actions', $user)
                        <div class="flex flex-row">
                            <x-button icon="o-check" class="btn-ghost btn-sm text-success"
                                tooltip-left="{{ __('Confirm presence') }}" />
                            <x-button icon="o-no-symbol" class="btn-ghost btn-sm text-warning"
                                tooltip-left="{{ __('No show') }}" />
                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error"
                                tooltip-left="{{ __('Cancel registration') }}" />
                        </div>
                        @endscope
                    </x-table>
                </x-tab>
                <x-tab name="wait-list" label="Liste d'attente" icon="o-clock">
                    <x-table :headers="[['key' => 'name', 'label' => 'Joueur']]" :rows="$waiting_list">
                        @scope('actions', $user)
                        <x-button icon="o-user-plus" class="btn-ghost btn-sm"
                            tooltip-left="{{ __('Validate registration') }}" />
                        @endscope
                    </x-table>
                </x-tab>
            </x-tabs>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-stat title="Inscrits" value="{{ count($registrated) }}" icon="o-user-group" />
            <x-stat title="Capacité" value="64" icon="o-receipt-percent"
                description="Places restantes : {{ 64 - count($registrated) }}" />
            <x-stat title="Attente" value="{{ count($waiting_list) }}" icon="o-clock" />
        </div>
    </div>
</x-tab>