<x-tournament.tournament-layout :breadcrumbs="$breadcrumbs" :tournament="$tournament" :statusesAllowed="$statusesAllowed">

           {{-- actions menu --}}
    @push('header-actions')
    <x-tournament.actions-menu :tournament="$tournament" :statusesAllowed="$statusesAllowed ?? []">    
        @include('admin.tournaments.partials.action-menu')
    </x-tournament.actions-menu>
    @endpush

    <div class="border-b pb-4 mb-6">
        <h3 class="text-2xl font-bold text-gray-800">{{ __('Phase finale') }} - {{ $tournament->name }}</h3>
    </div>

    <div class="knockout-bracket bg-gray-50 rounded-lg p-6 text-gray-500">
        @php
            // Déterminer tous les rounds existants
            $availableRounds = [];
            foreach ($rounds as $roundName => $matches) {
                if (count($matches) > 0 && $roundName !== 'bronze' && $roundName !== 'final') {
                    $availableRounds[] = $roundName;
                }
            }
            // Obtenir le nombre de rounds avant la finale
            $totalRounds = count($availableRounds);

            // Définir les noms des rounds en français
            $roundNames = [
                'round_64' => '64èmes de finale',
                'round_32' => '32èmes de finale',
                'round_16' => '16èmes de finale',
                'round_8' => '8èmes de finale',
                'quarterfinal' => 'Quarts de finale',
                'semifinal' => 'Demi-finales',
                'final' => 'Finale',
            ];

            // Ajouter toujours ces deux derniers rounds
            $finalRoundNames = ['Finale', '3ème place'];

        @endphp

        <div class="flex flex-row mb-4">
            <!-- Rounds headers -->
            @foreach ($availableRounds as $index => $roundName)
                <div class="w-1/{{ $totalRounds + 2 }} text-center font-bold p-2 text-gray-800">
                    @if ($index < $totalRounds - 1)
                        {{ $roundNames[$roundName] ?? $roundName }}
                    @else
                        Demi-finales
                    @endif
                </div>
            @endforeach

            <!-- Final and Bronze match headers -->
            @foreach ($finalRoundNames as $roundName)
                <div class="w-1/{{ $totalRounds + 2 }} text-center font-bold p-2 text-gray-800">{{ $roundName }}</div>
            @endforeach
        </div>
        <!-- Bracket structure -->
        <div class="grid grid-cols-{{ $totalRounds + 2 }} gap-4">
            <!-- Dynamic rounds -->
            @foreach ($availableRounds as $index => $roundName)
                <div class="{{ $index > 0 ? 'flex flex-col justify-around' : 'relative' }}">
                    @php
                        $matchCount = count($rounds[$roundName]);
                        $spacing = pow(2, $index);
                    @endphp

                    @foreach ($rounds[$roundName] as $match)
                        <div
                            class="match-box border border-gray-200 mb-{{ $spacing }} p-4 rounded-lg shadow-xs bg-white">
                            @include('admin.tournaments.partials.knockout-match', ['match' => $match])
                        </div>
                    @endforeach
                </div>
            @endforeach

            <!-- Final match -->
            <div class="flex flex-col justify-center">
                @if (isset($rounds['final']) && count($rounds['final']) > 0)
                    <div class="match-box border border-yellow-300 p-4 rounded-lg shadow-md bg-yellow-50">
                        @include('admin.tournaments.partials.knockout-match', [
                            'match' => $rounds['final'][0],
                        ])
                    </div>
                @endif
            </div>

            <!-- Bronze match -->
            <div class="flex flex-col justify-center">
                @if (isset($rounds['bronze']) && count($rounds['bronze']) > 0)
                    <div class="match-box border border-amber-300 p-4 rounded-lg shadow-md bg-amber-50">
                        @include('admin.tournaments.partials.knockout-match', [
                            'match' => $rounds['bronze'][0],
                        ])
                    </div>
                @endif
            </div>
        </div>
    </div>


    @push('modals')
    @endpush
</x-tournament.tournament-layout>
