<div>
    {{-- Header avec Recherche intégrée --}}
    <x-header progress-indicator separator title="Équipes">
        <x-slot:middle class="!justify-end">
        </x-slot:middle>
        <x-slot:actions>
            <x-input class="max-w-xs border-none" clearable icon="o-magnifying-glass" placeholder="Rechercher..."
                wire:model.live.debounce.300ms="search" />
            <x-button @click="$wire.teamModal = true" class="btn-primary" label="Nouvelle équipe" />
        </x-slot:actions>
    </x-header>

    {{-- Mobile view --}}
    <div>
        @php
            $groupedTeams = $teams->groupBy('category');
        @endphp

        @foreach ($groupedTeams as $category => $group)
            <section class="mb-8">

                {{-- Header catégorie --}}
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        {{ $category }}
                    </h2>

                    <span class="text-xs text-gray-400">
                        {{ $group->count() }} équipes
                    </span>
                </div>

                {{-- Grid --}}
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($group as $team)
                        <x-admin.club-events.teams.team-card :team="$team" />
                    @endforeach
                </div>

            </section>
        @endforeach
    </div>

    {{-- Large screen --}}
    {{-- <x-card class="bg-base-100 hidden border-none lg:block">
        <x-table :headers="$headers" :rows="$teams" hover>

            @scope('cell_name', $team)
                <span class="font-bold text-gray-900">{{ $team->name }}</span>
            @endscope

            @scope('cell_division', $team)
                <span class="text-xs font-medium uppercase tracking-tighter text-gray-500">{{ $team->division }}</span>
            @endscope

            @scope('cell_category', $team)
                <x-badge :value="$team->category" class="badge-ghost border-none text-[10px] font-bold uppercase" />
            @endscope

            @scope('cell_captain_name', $team)
                <span class="text-sm text-gray-600">{{ $team->captain_name }}</span>
            @endscope

            @scope('actions', $team)
                <x-button class="btn-xs btn-ghost hover:text-error text-gray-300" icon="o-trash" />
            @endscope

        </x-table>
    </x-card> --}}

    {{-- Modal Flat --}}
    <x-modal separator title="Nouvelle équipe" wire:model="teamModal">
        <div class="grid gap-y-5">
            <div class="flex gap-4">
                <x-input class="w-20" label="Lettre" wire:model="name" />
                <x-input class="flex-1" label="Division" wire:model="division" />
            </div>

            <x-select :options="[
                ['id' => 'H', 'name' => 'Hommes'],
                ['id' => 'D', 'name' => 'Dames'],
                ['id' => 'V', 'name' => 'Vétérans'],
            ]" label="Catégorie" wire:model="category" />

            <x-input label="Capitaine" wire:model="captain" />
        </div>

        <x-slot:actions>
            <x-button @click="$wire.teamModal = false" class="btn-ghost" label="Annuler" />
            <x-button class="btn-neutral" label="Enregistrer" wire:click="save" />
        </x-slot:actions>
    </x-modal>
</div>