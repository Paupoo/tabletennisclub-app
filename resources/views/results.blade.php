<x-guest-layout title="Résultats - {{ config('app.name') }}">
    <x-navigation :fixed="false" />
    
    <!-- Header -->
    <div class="bg-linear-to-r from-club-blue to-club-blue-light text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Résultats des Compétitions</h1>
            <p class="text-xl opacity-90">Suivez les performances de nos équipes dans toutes les compétitions</p>
        </div>
    </div>

    <!-- Season Filter -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ selectedSeason: '{{ $selectedSeason ?? '2024' }}' }">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="text-2xl font-bold">Résultats des Équipes</h2>
            <div class="flex items-center gap-2">
                <label for="season" class="text-sm font-medium">Saison:</label>
                <select x-model="selectedSeason" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                    @foreach($seasons ?? ['2024', '2023', '2022'] as $season)
                        <option value="{{ $season }}">{{ $season }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Results Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        @forelse($teams ?? [] as $team)
            <x-team-results :team="$team" />
        @empty
            <!-- Default teams if no data provided -->
            <x-team-results :team="[
                'name' => 'Équipe A - Division Premier',
                'position' => '2ème Place',
                'position_class' => 'bg-green-100 text-green-800',
                'matches' => [
                    ['date' => '15 Déc 2024', 'opponent' => 'Thunder TTC', 'venue' => 'Domicile', 'score' => '8-2', 'result' => 'Victoire'],
                    ['date' => '8 Déc 2024', 'opponent' => 'Elite Paddles', 'venue' => 'Extérieur', 'score' => '6-4', 'result' => 'Victoire'],
                    ['date' => '1 Déc 2024', 'opponent' => 'Spin Masters', 'venue' => 'Domicile', 'score' => '3-7', 'result' => 'Défaite'],
                ],
                'stats' => ['played' => 12, 'wins' => 9, 'losses' => 3, 'win_rate' => 75]
            ]" />
        @endforelse
    </div>
</x-guest-layout>
