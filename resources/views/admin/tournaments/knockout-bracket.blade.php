<!-- resources/views/tournament/knockout-bracket.blade.php -->
<x-app-layout>
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-center">
            <div class="w-full max-w-8xl">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6">
                        <a href="{{ route('tournamentSetup', $tournament) }}" 
                           class="inline-block mb-6 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200">
                            &larr; Retour au tournoi
                        </a>
                        
                        <div class="border-b pb-4 mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">{{ __('Phase finale') }} - {{ $tournament->name }}</h3>
                        </div>

                        @if(session('success'))
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                                <p>{{ session('success') }}</p>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                                <p>{{ session('error') }}</p>
                            </div>
                        @endif

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
                                    'final' => 'Finale'
                                ];
                                
                                // Ajouter toujours ces deux derniers rounds
                                $finalRoundNames = [
                                    'Finale',
                                    '3ème place'
                                ];
                                
                            @endphp

                            <div class="flex flex-row mb-4">
                                <!-- Rounds headers -->
                                @foreach($availableRounds as $index => $roundName)
                                    <div class="w-1/{{ $totalRounds + 2 }} text-center font-bold p-2 text-gray-800">
                                        @if($index < $totalRounds - 1)
                                            {{ $roundNames[$roundName] ?? $roundName }}
                                        @else
                                            Demi-finales
                                        @endif
                                    </div>
                                @endforeach
                                
                                <!-- Final and Bronze match headers -->
                                @foreach($finalRoundNames as $roundName)
                                    <div class="w-1/{{ $totalRounds + 2 }} text-center font-bold p-2 text-gray-800">{{ $roundName }}</div>
                                @endforeach
                            </div>

                            <!-- Bracket structure -->
                            <div class="flex flex-row h-full gap-4">
                                <!-- Dynamic rounds -->
                                @foreach($availableRounds as $index => $roundName)
                                    <div class="w-1/{{ $totalRounds + 2 }} {{ $index > 0 ? 'flex flex-col justify-around' : 'relative' }}">
                                        @php
                                            $matchCount = count($rounds[$roundName]);
                                            $spacing = pow(2, $index);
                                        @endphp
                                        
                                        @foreach($rounds[$roundName] as $match)
                                            <div class="match-box border border-gray-200 mb-{{ $spacing }} p-4 rounded-lg shadow-sm bg-white">
                                                @include('admin.tournaments.partials.knockout-match', ['match' => $match])
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach

                                <!-- Final match -->
                                <div class="w-1/{{ $totalRounds + 2 }} flex flex-col justify-center">
                                    @if(isset($rounds['final']) && count($rounds['final']) > 0)
                                        <div class="match-box border border-yellow-300 p-4 rounded-lg shadow-md bg-yellow-50">
                                            @include('admin.tournaments.partials.knockout-match', ['match' => $rounds['final'][0]])
                                        </div>
                                    @endif
                                </div>

                                <!-- Bronze match -->
                                <div class="w-1/{{ $totalRounds + 2 }} flex flex-col justify-center">
                                    @if(isset($rounds['bronze']) && count($rounds['bronze']) > 0)
                                        <div class="match-box border border-amber-300 p-4 rounded-lg shadow-md bg-amber-50">
                                            @include('admin.tournaments.partials.knockout-match', ['match' => $rounds['bronze'][0]])
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>