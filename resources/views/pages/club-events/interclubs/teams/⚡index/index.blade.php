<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>
    <x-header progress-indicator separator title="Équipes">
        <x-slot:middle class="justify-end!">
            @if ($season)
                <span class="hidden text-sm text-gray-500 sm:block">Saison {{ $season->name }}</span>
            @endif
        </x-slot:middle>
        <x-slot:actions>
            <x-input class="max-w-xs border-none" clearable icon="o-magnifying-glass" placeholder="Rechercher..."
                wire:model.live.debounce.300ms="search" />
            <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams.builder') }}" icon="o-squares-plus"
                label="Composer les équipes" />
            <x-button class="btn-primary btn-sm" icon="o-plus" label="Nouvelle équipe"
                wire:click="$set('createModal', true)" />
            @if ($teamsCount > 0)
                <x-button class="btn-ghost text-error" icon="o-trash"
                    label="Tout supprimer" wire:click="$set('deleteAllModal', true)" />
            @endif
        </x-slot:actions>
    </x-header>

    @if ($teams->isEmpty())
        <x-card class="border-none">
            <div class="py-16 text-center text-gray-500">
                @if ($season)
                    Aucune équipe pour la saison {{ $season->name }}.
                    <div class="mt-4">
                        <x-button class="btn-primary" link="{{ route('admin.interclubs.teams.builder') }}"
                            icon="o-squares-plus" label="Composer les équipes" />
                    </div>
                @else
                    Aucune saison active. Activez une saison pour gérer les équipes.
                @endif
            </div>
        </x-card>
    @else
        @php $groupedTeams = $teams->groupBy('category'); @endphp

        @foreach ($groupedTeams as $category => $group)
            <section class="mb-8">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        {{ $category }}
                    </h2>
                    <span class="text-xs text-gray-400">{{ $group->count() }} équipe{{ $group->count() > 1 ? 's' : '' }}</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($group as $team)
                        <x-admin.club-events.teams.team-card :team="$team" wire:key="team-{{ $team->id }}" />
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif

    {{-- Modal création libre --}}
    <x-modal title="Nouvelle équipe" wire:model="createModal">
        <div class="space-y-4">
            <x-select label="Lettre" :options="$teamNameOptions" wire:model="newTeamName"
                placeholder="Choisir A – Z" />
            <x-select label="Catégorie" :options="$categoryOptions" wire:model="newCategory"
                placeholder="Sélectionner..." />
            <x-select label="Niveau" :options="$levelOptions" wire:model="newLevel"
                placeholder="Sélectionner..." />
            <x-input label="Division" wire:model="newDivision" placeholder="ex: 3B" />
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('createModal', false)" />
            <x-button class="btn-primary" label="Créer" wire:click="createTeam" spinner />
        </x-slot:actions>
    </x-modal>

    {{-- Modal suppression unitaire --}}
    <x-modal subtitle="{{ __('Warning!') }}" title="Confirmer la suppression" wire:model="deleteModal">
        <p>Êtes-vous sûr de vouloir supprimer cette équipe ? Cette action est irréversible.</p>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="Supprimer" spinner wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    {{-- Modal suppression totale --}}
    <x-modal subtitle="{{ __('Warning!') }}" title="Supprimer toutes les équipes" wire:model="deleteAllModal">
        <p>
            Êtes-vous sûr de vouloir supprimer <strong>toutes les équipes</strong> de la saison en cours ?
            Cette action est irréversible et retirera également tous les joueurs de leurs équipes.
        </p>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('deleteAllModal', false)" />
            <x-button class="btn-error" label="Tout supprimer" spinner wire:click="deleteAll" />
        </x-slot:actions>
    </x-modal>
</div>
