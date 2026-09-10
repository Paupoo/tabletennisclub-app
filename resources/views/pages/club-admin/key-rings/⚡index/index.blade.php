<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Key rings')" :subtitle="__('Who holds which key ring, and which ones are in the drawer')"
        separator progress-indicator>
        <x-slot:actions>
            <x-button :label="__('Create a key ring')" icon="o-plus" class="btn-primary btn-sm"
                wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    <div class="mt-4 flex items-center justify-between gap-4">
        <p class="text-sm text-base-content/60">
            {{ trans_choice('{0}No key ring yet|{1}1 key ring|[2,*]:count key rings', $keyRings->whereNull('deleted_at')->count(), ['count' => $keyRings->whereNull('deleted_at')->count()]) }}
            ·
            {{ trans_choice('{0}none assigned|{1}1 assigned|[2,*]:count assigned', $keyRings->whereNull('deleted_at')->whereNotNull('held_by_user_id')->count(), ['count' => $keyRings->whereNull('deleted_at')->whereNotNull('held_by_user_id')->count()]) }}
        </p>
        <x-checkbox :label="__('Show retired key rings')" wire:model.live="showRetired" class="text-sm" />
    </div>

    <x-card class="mt-4 shadow-sm">
        @forelse ($keyRings as $keyRing)
            <div wire:key="key-ring-{{ $keyRing->id }}"
                @class([
                    'flex flex-wrap items-center gap-3 border-b border-base-300 py-3 last:border-b-0',
                    'opacity-60' => $keyRing->trashed(),
                ])>
                <x-icon name="o-key" class="h-5 w-5 shrink-0 text-base-content/60" />

                <span class="font-bold">{{ $keyRing->label() }}</span>

                @if ($keyRing->trashed())
                    <x-badge :value="__('Retired')" class="badge-soft badge-warning badge-sm" />
                @endif

                <span class="text-base-content/60">→</span>

                @if ($keyRing->heldBy)
                    <span class="text-sm">{{ $keyRing->heldBy->first_name }} {{ $keyRing->heldBy->last_name }}</span>
                @else
                    <span class="text-sm italic text-base-content/60">{{ __('In the drawer') }}</span>
                @endif

                @if ($keyRing->notes)
                    <span class="text-sm text-base-content/60">{{ $keyRing->notes }}</span>
                @endif

                <div class="ml-auto flex items-center gap-2">
                    @if ($keyRing->trashed())
                        <x-button :label="__('Put back in service')" icon="o-arrow-path" class="btn-ghost btn-sm"
                            wire:click="restoreKeyRing({{ $keyRing->id }})" />
                    @else
                        <x-button :label="__('Move')" icon="o-arrows-right-left" class="btn-ghost btn-sm"
                            wire:click="openMove({{ $keyRing->id }})" />
                        <x-button :label="__('Retire')" icon="o-archive-box-x-mark" class="btn-ghost btn-sm text-error"
                            wire:click="openRetire({{ $keyRing->id }})" />
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state icon="o-key" :heading="__('No key ring yet')"
                :message="__('Create the first key ring to start tracking who can open the venue.')" />
        @endforelse
    </x-card>

    {{-- Create --}}
    <x-app-modal wire:model="createModal" :title="__('Create a key ring')"
        :subtitle="__('The number is assigned automatically and never reused.')" separator :open="$createModal">
        <x-form wire:submit="createKeyRing">
            <x-choices-offline wire:model="newHolderUserId" :label="__('Holder')" :options="$holderOptions" single
                searchable clearable :placeholder="__('Leave empty to keep it in the drawer')" />
            <x-textarea wire:model="newNotes" :label="__('Notes')"
                :hint="__('What this ring opens, where it is kept — optional.')" rows="2" />

            <x-slot:actions>
                <x-button :label="__('Cancel')" @click="$wire.createModal = false" />
                <x-button :label="__('Create')" class="btn-primary" type="submit" spinner="createKeyRing" />
            </x-slot:actions>
        </x-form>
    </x-app-modal>

    {{-- Move --}}
    <x-app-modal wire:model="moveModal" :title="__('Move this key ring')"
        :subtitle="__('Handing it over records who holds it. It opens nothing new by itself.')" separator
        :open="$moveModal">
        <x-form wire:submit="moveKeyRing">
            @if ($selectedKeyRing)
                <p class="text-sm text-base-content/60">
                    {{ __('Currently held by:') }}
                    <span class="font-bold text-base-content">
                        @if ($selectedKeyRing->heldBy)
                            {{ $selectedKeyRing->heldBy->first_name }} {{ $selectedKeyRing->heldBy->last_name }}
                        @else
                            {{ __('In the drawer') }}
                        @endif
                    </span>
                </p>
            @endif
            <x-choices-offline wire:model="targetHolderUserId" :label="__('New holder')" :options="$holderOptions" single
                searchable clearable :placeholder="__('Leave empty to put it back in the drawer')" />

            <x-slot:actions>
                <x-button :label="__('Cancel')" @click="$wire.moveModal = false" />
                <x-button :label="__('Move')" class="btn-primary" type="submit" spinner="moveKeyRing" />
            </x-slot:actions>
        </x-form>
    </x-app-modal>

    {{-- Retire --}}
    <x-app-modal wire:model="retireModal" :title="__('Retire this key ring')"
        :subtitle="__('It leaves the list but keeps its number and its last holder. You can put it back later.')"
        separator :open="$retireModal">
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.retireModal = false" />
            <x-button :label="__('Retire')" class="btn-error" wire:click="retireKeyRing" spinner="retireKeyRing" />
        </x-slot:actions>
    </x-app-modal>
</div>
