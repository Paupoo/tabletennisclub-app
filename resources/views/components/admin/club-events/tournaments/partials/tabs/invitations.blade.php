<x-tab name="2" label="{{ __('Invitations') }}" icon="o-envelope">
    <div class="mt-8 animate-in fade-in duration-500">
        <x-card title="{{ __('Members list') }}" size="md">
            <x-slot:menu>
                <div class="flex gap-2">
                    <x-button label="Tous" icon="o-check" class="btn-sm btn-ghost" wire:click="selectAllMembers" />
                    <x-button label="Aucun" icon="o-x-mark" class="btn-sm btn-ghost" wire:click="selectNoMembers" />
                    <x-button label="Envoyer les invitations" icon="o-paper-airplane" class="btn-primary btn-sm"
                        @click="$wire.showInviteModal = true" :disabled="count($selectedMembers) === 0" />
                </div>
            </x-slot:menu>

            <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-6">
                <x-input placeholder="Rechercher un membre..." icon="o-magnifying-glass"
                    wire:model.live.debounce.300ms="memberSearch" class="max-w-sm" clearable />
                {{-- Bouton pour ouvrir le modal --}}
                <x-badge value="{{ count($selectedMembers) }} membres sélectionnés" class="badge-primary p-4" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($filteredMembers as $member)
                    <div wire:key="member-{{ $member['id'] }}" wire:click="toggleMember({{ $member['id'] }})"
                        class="flex items-center gap-4 p-4 rounded-2xl border-2 cursor-pointer transition-all {{ in_array($member['id'], $selectedMembers) ? 'border-primary bg-primary/5' : 'border-base-200 hover:border-primary/30' }}">
                        <x-avatar :placeholder="strtoupper(substr($member['name'], 0, 2))" class="!w-10 !h-10 rounded-lg" />
                        <div class="flex-1 min-w-0">
                            <p class="font-bold truncate text-sm">{{ $member['name'] }}</p>
                            <p class="text-xs opacity-50">Niv. {{ $member['ranking'] }}</p>
                        </div>
                        @if (in_array($member['id'], $selectedMembers))
                            <x-icon name="o-check-circle" class="w-6 h-6 text-primary" />
                        @else
                            <x-icon name="o-plus" class="w-5 h-5 opacity-20" />
                        @endif

                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Section Historique des envois --}}
        <x-card title="{{ __('Sent invitations') }}" icon="o-history" separator class="mt-8 shadow-sm"
            x-data="{ open: false }">
            <x-slot:menu>
                <x-button :label="__('View history')" icon="o-eye" class="btn-sm btn-ghost" @click="open = !open" />
            </x-slot:menu>

            {{-- Liste compacte (affichée au clic) --}}
            <div x-show="open" x-transition class="space-y-4">
                @forelse($invitationHistory as $batch)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-base-200/50 border border-base-300">
                        <div class="flex items-center gap-4">
                            <div class="bg-primary/10 p-2 rounded-lg">
                                <x-icon name="o-paper-airplane" class="w-5 h-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-sm font-bold">{{ $batch['count'] }} invitations envoyées</p>
                                <p class="text-xs opacity-50">
                                    {{ \Carbon\Carbon::parse($batch['sent_at'])->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-badge :value="$batch['status']" class="badge-success badge-outline badge-sm" />
                            <x-button icon="o-information-circle" class="btn-circle btn-xs btn-ghost"
                                wire:click="viewBatchDetails({{ $batch['id'] }})" />
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 opacity-40">
                        <x-icon name="o-envelope" class="w-8 h-8 mx-auto mb-2" />
                        <p class="text-sm">Aucune invitation envoyée pour le moment.</p>
                    </div>
                @endforelse
            </div>
        </x-card>

        {{-- Public article --}}
        <x-card title="{{ __('Public article') }}" separator class="mt-8 shadow-sm">
            <x-slot:menu>
                <x-button label="{{ __('Publish article') }}" icon="o-paper-airplane" class="btn-primary btn-sm"
                    @click="$wire.showPublishModal = true" />
            </x-slot:menu>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Section Gauche : Contenu --}}
                <div class="space-y-4">
                    <x-input label="{{ __('Title') }}" placeholder="Ex: Grand Tournoi de Printemps 2026"
                        wire:model="name" />
                    <x-textarea label="{{ __('Article content') }} }}" wire:model="articleContent"
                        placeholder="Décrivez le tournoi..." />
                    <x-datepicker type="date" label="{{ __('Publication date') }}" icon="o-calendar" native
                        wire:model="publicationDate" />
                </div>

                {{-- Section Droite : Paramètres & Image --}}
                <div class="space-y-6">
                    {{-- Mockup Upload Image --}}
                    <div
                        class="border-2 border-dashed border-base-300 rounded-2xl p-8 text-center hover:border-primary transition-colors cursor-pointer bg-base-200/50">
                        <x-icon name="o-camera" class="w-10 h-10 mx-auto opacity-20" />
                        <p class="text-xs mt-2 opacity-50">Cliquez pour ajouter une image de couverture
                        </p>
                    </div>
                    <div class="bg-base-200/30 p-4 rounded-xl space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium">Inscriptions publiques</span>
                            <x-badge :value="$publicRegistration ? 'Activé' : 'Désactivé'" :class="$publicRegistration ? 'badge-success' : 'badge-ghost'" />
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>
</x-tab>
