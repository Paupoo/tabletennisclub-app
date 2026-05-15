<div class="mt-8 animate-in fade-in duration-500">

    @if ($this->isLaunched)
        <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-base-200 border border-base-300 text-sm">
            <x-icon name="o-lock-closed" class="w-5 h-5 shrink-0 text-base-content/40" />
            <p class="text-base-content/60">{{ __('The tournament has been launched. Invitations are read-only.') }}</p>
        </div>
    @endif

    {{-- Registration deadline reminder --}}
    @if ($registration_deadline)
        <div class="mb-6 flex items-center gap-3 p-3 rounded-xl bg-info/5 border border-info/20 text-sm">
            <x-icon name="o-calendar-days" class="w-5 h-5 text-info shrink-0" />
            <span class="text-base-content/70">{{ __('Registration deadline:') }}</span>
            <span class="font-semibold">{{ \Carbon\Carbon::parse($registration_deadline)->format('d/m/Y') }}</span>
        </div>
    @else
        <x-alert
            title="{{ __('Registration deadline not set') }}"
            description="{{ __('Please set a registration deadline in step 1 before sending invitations.') }}"
            icon="o-exclamation-triangle"
            class="alert-warning alert-soft mb-6" />
    @endif

    <x-card title="{{ __('Members list') }}" size="md">
        <x-slot:menu>
            @if (! $this->isLaunched)
                <div class="flex gap-2">
                    <x-button label="Tous" icon="o-check" class="btn-sm btn-ghost" wire:click="selectAllMembers" />
                    <x-button label="Aucun" icon="o-x-mark" class="btn-sm btn-ghost" wire:click="selectNoMembers" />
                    <x-button label="Envoyer les invitations" icon="o-paper-airplane" class="btn-primary btn-sm"
                        @click="$wire.showInviteModal = true" :disabled="count($selectedMembers) === 0" />
                </div>
            @endif
        </x-slot:menu>

        <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-6">
            <x-input placeholder="Rechercher un membre..." icon="o-magnifying-glass"
                wire:model.live.debounce.300ms="memberSearch" class="max-w-sm" clearable />
            <x-badge value="{{ count($selectedMembers) }} membres sélectionnés" class="badge-primary p-4" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($filteredMembers as $member)
                <div wire:key="member-{{ $member['id'] }}" wire:click="toggleMember({{ $member['id'] }})"
                    class="flex items-center gap-4 p-4 rounded-2xl border-2 cursor-pointer transition-all {{ in_array($member['id'], $selectedMembers) ? 'border-primary bg-primary/5' : 'border-base-200 hover:border-primary/30' }}">
                    <x-avatar :placeholder="strtoupper(substr($member['name'], 0, 2))" class="!w-10 !h-10 rounded-lg" />
                    <div class="flex-1 min-w-0">
                        <p class="font-bold truncate text-sm">{{ $member['name'] }}</p>
                        <p class="text-xs opacity-50">{{ $member['ranking'] }}</p>
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

    {{-- Invitation history --}}
    <x-card title="{{ __('Sent invitations') }}" icon="o-history" separator class="mt-8 shadow-sm"
        x-data="{ open: false }">
        <x-slot:menu>
            <x-button :label="__('View history')" icon="o-eye" class="btn-sm btn-ghost" @click="open = !open" />
        </x-slot:menu>

        <div x-show="open" x-transition class="space-y-4">
            @forelse($this->invitationHistory as $batch)
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

</div>
