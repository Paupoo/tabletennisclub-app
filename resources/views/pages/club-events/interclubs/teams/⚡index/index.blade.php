<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>
    <x-header progress-indicator separator :title="__('Teams')">
        <x-slot:middle class="justify-end!">
            <x-select
                :options="$seasons"
                option-label="name"
                option-value="id"
                wire:model.live="selectedSeasonId"
                :placeholder="__('Select a season')" />
        </x-slot:middle>
        <x-slot:actions>
            <x-input class="max-w-xs border-none" clearable icon="o-magnifying-glass" placeholder="Rechercher..."
                wire:model.live.debounce.300ms="search" />
            @if ($isAdminOrCommittee)
                <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams.builder') }}" icon="o-squares-plus"
                    :label="__('Build teams')" />
                <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New team')"
                    wire:click="$set('createModal', true)" />
                @if ($teamsCount > 0)
                    <x-button class="btn-ghost text-error" icon="o-trash"
                        label="Tout supprimer" wire:click="$set('deleteAllModal', true)" />
                @endif
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
                            icon="o-squares-plus" :label="__('Build teams')" />
                    </div>
                @else
                    Aucune saison active. Activez une saison pour gérer les équipes.
                @endif
            </div>
        </x-card>
    @else
        @php
            $groupedTeams = $teams->groupBy('category');
        @endphp

        @php
            $catColor = ['Hommes' => 'blue', 'Vétérans' => 'amber', 'Dames' => 'pink'];
        @endphp
        <div class="space-y-10">
            @foreach ($groupedTeams as $category => $group)
                <x-section-accordion
                    :label="$category"
                    :count="$group->count() . ' équipe' . ($group->count() > 1 ? 's' : '')"
                    :color="$catColor[$category] ?? 'gray'">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 pb-2">
                        @foreach ($group as $team)
                            <x-admin.club-events.teams.team-card :team="$team" wire:key="team-{{ $team->id }}" />
                        @endforeach
                    </div>
                </x-section-accordion>
            @endforeach
        </div>
    @endif

    {{-- Modal création libre --}}
    <x-modal :title="__('New team')" wire:model="createModal">
        <div class="space-y-4">
            <x-select label="Lettre" :options="$teamNameOptions" wire:model="newTeamName"
                placeholder="Choisir A – Z" />
            <x-select :label="__('Category')" :options="$categoryOptions" wire:model="newCategory"
                placeholder="Sélectionner..." />
            <x-select label="Niveau" :options="$levelOptions" wire:model="newLevel"
                placeholder="Sélectionner..." />
            <x-input label="Division" wire:model="newDivision" placeholder="ex: 3B" />
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('createModal', false)" />
            <x-button class="btn-primary" :label="__('Create')" wire:click="createTeam" spinner />
        </x-slot:actions>
    </x-modal>

    <x-confirm-modal model="deleteModal" :title="__('Delete this team?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('Are you sure you want to delete this team? This action is irreversible.') }}</p>
    </x-confirm-modal>

    <x-confirm-modal model="deleteAllModal" :title="__('Delete all teams?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete all')" confirmAction="deleteAll">
        <p>
            {{ __('Are you sure you want to delete') }} <strong>{{ __('all teams') }}</strong>
            {{ __('for the current season? This action is irreversible and will also remove all players from their teams.') }}
        </p>
    </x-confirm-modal>
</div>
