{{-- Shared members list + invitation history for both tabs and steps views.
     Pass ['isLocked' => true] when including to hide actions after tournament launch. --}}

<x-card :title="__('Members list')" size="md">
    <x-slot:menu>
        @if (! $isLocked)
            <div class="flex gap-2">
                <x-button :label="__('All')" icon="o-check" class="btn-sm btn-ghost" wire:click="selectAllMembers" />
                <x-button :label="__('None')" icon="o-x-mark" class="btn-sm btn-ghost" wire:click="selectNoMembers" />
                <x-button :label="__('Send invitations')" icon="o-paper-airplane" class="btn-primary btn-sm"
                    @click="$wire.showInviteModal = true" :disabled="count($selectedMembers) === 0" />
            </div>
        @endif
    </x-slot:menu>

    <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-6">
        <x-input :placeholder="__('Search a member…')" icon="o-magnifying-glass"
            wire:model.live.debounce.300ms="memberSearch" class="max-w-sm" clearable />
        <x-badge :value="trans_choice('{0} No member selected|{1} :count member selected|[2,*] :count members selected', count($selectedMembers), ['count' => count($selectedMembers)])"
            class="badge-primary p-4" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($filteredMembers as $member)
            <button type="button" wire:key="member-{{ $member['id'] }}" wire:click="toggleMember({{ $member['id'] }})"
                aria-pressed="{{ in_array($member['id'], $selectedMembers) ? 'true' : 'false' }}"
                class="w-full text-left flex items-center gap-4 p-4 rounded-2xl border-2 cursor-pointer transition-all {{ in_array($member['id'], $selectedMembers) ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-primary/30' }}">
                <x-avatar :placeholder="strtoupper(substr($member['name'], 0, 2))" class="w-10! h-10! rounded-lg" />
                <div class="flex-1 min-w-0">
                    <p class="font-bold truncate text-sm">{{ $member['name'] }}</p>
                    <p class="text-xs text-muted">{{ $member['ranking'] }}</p>
                </div>
                @if (in_array($member['id'], $selectedMembers))
                    <x-icon name="o-check-circle" class="w-6 h-6 text-primary" />
                @else
                    <x-icon name="o-plus" class="w-5 h-5 opacity-20" />
                @endif
            </button>
        @endforeach
    </div>
</x-card>

{{-- Invitation history --}}
<x-card :title="__('Sent invitations')" icon="o-history" separator class="mt-8 shadow-sm"
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
                        <p class="text-sm font-bold">{{ trans_choice('{1} :count invitation sent|[2,*] :count invitations sent', $batch['count'], ['count' => $batch['count']]) }}</p>
                        <p class="text-xs text-muted">
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
            <div class="text-center py-6 text-muted">
                <x-icon name="o-envelope" class="w-8 h-8 mx-auto mb-2" />
                <p class="text-sm">{{ __('No invitations sent yet.') }}</p>
            </div>
        @endforelse
    </div>
</x-card>
