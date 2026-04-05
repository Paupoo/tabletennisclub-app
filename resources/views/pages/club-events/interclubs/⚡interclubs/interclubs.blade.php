<div>
    @if (!$selectedTeamId)

    {{-- ================================================================
             VUE LISTE DES ÉQUIPES
        ================================================================ --}}
    <x-header title="{{ __('Interblub Results') }}" subtitle="{{ __('Manage teams and results') }}" separator>
        <x-slot:actions>
            <x-button label="{{ __('New team') }}" icon="o-plus" class="btn-primary btn-sm"
                wire:click="createTeam" />
        </x-slot:actions>
    </x-header>

    {{-- MOBILE : Cartes --}}
    <div class="grid grid-cols-1 gap-4 md:hidden">
        @foreach ($teams as $team)
        <x-card class="shadow-sm border border-base-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-bold">{{ $team['name'] }}</p>
                    <p class="text-sm text-gray-500">{{ $team['division'] }}</p>
                </div>
                <x-badge :value="$team['season']" class="badge-outline text-xs" />
            </div>
            <x-slot:actions>
                <x-button icon="o-list-bullet" wire:click="selectTeam({{ $team['id'] }})"
                    class="btn-sm btn-ghost text-info" tooltip="{{ __('View matches') }}" />
                <x-button icon="o-pencil" wire:click="editTeam({{ $team['id'] }})"
                    class="btn-sm btn-ghost" tooltip="{{ __('Edit') }}" />
                <x-button icon="o-trash" wire:click="deleteTeam({{ $team['id'] }})"
                    wire:confirm="{{ __('Delete this team and all its matches?') }}"
                    class="btn-sm btn-ghost text-error" tooltip="{{ __('Delete') }}" />
            </x-slot:actions>
        </x-card>
        @endforeach
    </div>

    {{-- DESKTOP : Tableau --}}
    <div class="hidden md:block">
        <x-table :headers="$teamHeaders" :rows="$teams">
            @scope('actions', $team)
            <div class="flex gap-2">
                <x-button icon="o-list-bullet" wire:click="selectTeam({{ $team['id'] }})"
                    class="btn-sm btn-ghost text-info" tooltip="{{ __('View matches') }}" />
                <x-button icon="o-pencil" wire:click="editTeam({{ $team['id'] }})"
                    class="btn-sm btn-ghost" tooltip="{{ __('Edit') }}" />
                <x-button icon="o-trash" wire:click="deleteTeam({{ $team['id'] }})"
                    wire:confirm="{{ __('Delete this team and all its matches?') }}"
                    class="btn-sm btn-ghost text-error" tooltip="{{ __('Delete') }}" />
            </div>
            @endscope
        </x-table>
    </div>

    @else

    {{-- ================================================================
             VUE MATCHS D'UNE ÉQUIPE
        ================================================================ --}}
    <x-header
        title="{{ $this->selectedTeam['name'] }} — {{ $this->selectedTeam['division'] }}"
        subtitle="{{ $this->selectedTeam['season'] }}"
        separator>
        <x-slot:actions>
            <x-button label="{{ __('Back') }}" icon="o-arrow-left" wire:click="backToList"
                class="btn-ghost btn-sm" />
            <x-button label="{{ __('Add match') }}" icon="o-plus" wire:click="createMatch"
                class="btn-primary btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat title="{{ __('Played') }}" value="{{ $this->selectedTeamStats['played'] }}" icon="o-calendar" />
        <x-stat title="{{ __('Wins') }}" value="{{ $this->selectedTeamStats['wins'] }}" icon="o-trophy" color="text-success" />
        <x-stat title="{{ __('Losses') }}" value="{{ $this->selectedTeamStats['losses'] }}" icon="o-x-circle" color="text-error" />
        <x-stat title="{{ __('Win rate') }}" value="{{ $this->selectedTeamStats['win_rate'] }}%" icon="o-chart-bar" color="text-primary" />
    </div>

    {{-- Liste des matchs --}}
    <x-card>
        {{-- MOBILE --}}
        <div class="space-y-3 md:hidden">
            @forelse ($this->selectedTeamMatches as $match)
            @php
            $hasScore = $match['score_home'] !== null;
            $result = $hasScore ? $this->resolveResult($match) : null;
            $badgeClass = match($result) {
            'win' => 'badge-success',
            'loss' => 'badge-error',
            'draw' => 'badge-warning',
            default => 'badge-ghost',
            };
            $resultLabel = match($result) {
            'win' => __('Win'),
            'loss' => __('Loss'),
            'draw' => __('Draw'),
            default => __('Scheduled'),
            };
            @endphp
            <div class="flex items-center justify-between py-2 border-b last:border-0">
                <div>
                    <p class="font-medium text-sm">{{ $match['opponent'] }}</p>
                    <p class="text-xs text-gray-500">
                        {{ \Carbon\Carbon::parse($match['date'])->format('d/m/Y') }}
                        · {{ $match['venue'] === 'home' ? __('Home') : __('Away') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($hasScore)
                    <span class="font-mono font-bold text-sm">
                        {{ $match['score_home'] }}-{{ $match['score_away'] }}
                    </span>
                    <x-badge :value="$resultLabel" :class="$badgeClass" />
                    @else
                    <x-badge value="{{ __('Scheduled') }}" class="badge-ghost" />
                    @endif
                    <x-button icon="o-pencil" wire:click="editMatch({{ $match['id'] }})"
                        class="btn-xs btn-ghost" />
                    <x-button icon="o-trash" wire:click="deleteMatch({{ $match['id'] }})"
                        wire:confirm="{{ __('Are you sure?') }}" class="btn-xs btn-ghost text-error" />
                </div>
            </div>
            @empty
            <div class="text-center py-10 text-gray-400">
                <x-icon name="o-calendar" class="w-10 h-10 mx-auto mb-2" />
                <p>{{ __('No matches yet.') }}</p>
            </div>
            @endforelse
        </div>

        {{-- DESKTOP --}}
        <div class="hidden md:block">
            <x-table :headers="$matchHeaders" :rows="$this->selectedTeamMatches">
                @scope('cell_venue', $match)
                {{ $match['venue'] === 'home' ? __('Home') : __('Away') }}
                @endscope

                @scope('cell_score', $match)
                @if ($match['score_home'] !== null)
                <span class="font-mono font-bold">
                    {{ $match['score_home'] }}-{{ $match['score_away'] }}
                </span>
                @else
                <span class="text-gray-400 italic text-sm">{{ __('TBD') }}</span>
                @endif
                @endscope

                @scope('cell_result', $match)
                @if ($match['score_home'] !== null)
                @php
                $result = $this->resolveResult($match);
                $badgeClass = match($result) {
                'win' => 'badge-success',
                'loss' => 'badge-error',
                'draw' => 'badge-warning',
                };
                $label = match($result) {
                'win' => __('Win'),
                'loss' => __('Loss'),
                'draw' => __('Draw'),
                };
                @endphp
                <x-badge :value="$label" :class="$badgeClass" />
                @else
                <x-badge value="{{ __('Scheduled') }}" class="badge-ghost" />
                @endif
                @endscope

                @scope('actions', $match)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="editMatch({{ $match['id'] }})"
                        class="btn-sm btn-ghost" tooltip="{{ __('Edit') }}" />
                    <x-button icon="o-trash" wire:click="deleteMatch({{ $match['id'] }})"
                        wire:confirm="{{ __('Are you sure?') }}"
                        class="btn-sm btn-ghost text-error" tooltip="{{ __('Delete') }}" />
                </div>
                @endscope
            </x-table>
        </div>
    </x-card>

    @endif

    {{-- ================================================================
         MODALE — ÉQUIPE
    ================================================================ --}}
    <x-modal wire:model="showTeamModal" title="{{ __('Team') }}" separator>
        <div class="grid gap-4">
            <x-input label="{{ __('Name') }}" placeholder="{{ __('E.g. Team A') }}"
                wire:model="teamForm.name" />
            <x-input label="{{ __('Division') }}" placeholder="{{ __('E.g. Division 2C') }}"
                wire:model="teamForm.division" />
            <x-input label="{{ __('Position') }}" placeholder="{{ __('E.g. 2nd place') }}"
                wire:model="teamForm.position" />
            <x-select label="{{ __('Season') }}" :options="$seasonOptions"
                wire:model="teamForm.season" />
        </div>
        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.showTeamModal = false" />
            <x-button label="{{ __('Save') }}" wire:click="saveTeam"
                class="btn-primary" icon="o-paper-airplane" />
        </x-slot:actions>
    </x-modal>

    {{-- ================================================================
         MODALE — MATCH
    ================================================================ --}}
    <x-modal wire:model="showMatchModal" title="{{ __('Match') }}" separator>
        <div class="grid gap-4">
            <x-input label="{{ __('Opponent') }}" placeholder="{{ __('E.g. Arc En Ciel F') }}"
                wire:model="matchForm.opponent" />

            <div class="grid grid-cols-2 gap-4">
                <x-datepicker label="{{ __('Date') }}" wire:model="matchForm.date" icon="o-calendar" />
                <x-select label="{{ __('Venue') }}" :options="$venueOptions" wire:model="matchForm.venue" />
            </div>

            {{-- Score : optionnel (match pas encore joué) --}}
            <p class="text-sm font-medium text-gray-600">
                {{ __('Score') }}
                <span class="text-xs font-normal text-gray-400">({{ __('leave empty if not played yet') }})</span>
            </p>
            <div class="grid grid-cols-2 gap-4">
                <x-input label="{{ __('Home') }}" type="number" min="0"
                    wire:model="matchForm.score_home" placeholder="—" />
                <x-input label="{{ __('Away') }}" type="number" min="0"
                    wire:model="matchForm.score_away" placeholder="—" />
            </div>

            {{-- Aperçu du résultat calculé --}}
            @if ($matchForm['score_home'] !== null && $matchForm['score_away'] !== null)
            @php
            $preview = $this->resolveResult($matchForm);
            $previewLabel = match($preview) {
            'win' => '✅ ' . __('Win'),
            'loss' => '❌ ' . __('Loss'),
            'draw' => '🟡 ' . __('Draw'),
            };
            @endphp
            <div class="rounded-lg bg-base-200 px-4 py-2 text-sm font-medium">
                {{ __('Calculated result') }} : {{ $previewLabel }}
            </div>
            @endif
        </div>
        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.showMatchModal = false" />
            <x-button label="{{ __('Save') }}" wire:click="saveMatch"
                class="btn-primary" icon="o-paper-airplane" />
        </x-slot:actions>
    </x-modal>
</div>