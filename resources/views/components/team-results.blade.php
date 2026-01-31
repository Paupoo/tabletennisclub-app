@props(['team'])

<div class="mb-12">
    <div class="bg-white rounded-lg shadow-xs border p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-club-blue">{{ $team['name'] }}</h3>
            <div class="{{ $team['position_class'] ?? 'bg-green-100 text-green-800' }} px-3 py-1 rounded-full text-sm font-medium text-center">
                {{ $team['position'] }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-semibold">Date</th>
                        <th class="text-left py-3 px-4 font-semibold">Adversaire</th>
                        <th class="text-left py-3 px-4 font-semibold hidden md:block">Domicile/Extérieur</th>
                        <th class="text-left py-3 px-4 font-semibold">Score</th>
                        <th class="text-left py-3 px-4 font-semibold hidden md:block">Résultat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($team['matches'] as $match)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4 hidden md:block">{{ $match['date'] }}</td>
                            <td class="py-3 px-4 block md:hidden">13-12-24</td>
                            <td class="py-3 px-4">{{ $match['opponent'] }}</td>
                            <td class="py-3 px-4 hidden md:block">{{ $match['venue'] }}</td>
                            <td class="py-3 px-4 font-mono ">{{ $match['score'] }}</td>
                            <td class="py-3 px-4 hidden md:block">
                                <span class="@if($match['result'] === 'Victoire') bg-green-100 text-green-800 @elseif($match['result'] === 'Défaite') bg-red-100 text-red-800 @else bg-gray-100 text-gray-800 @endif px-2 py-1 rounded-sm text-sm font-medium">
                                    {{ $match['result'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-club-blue">{{ $team['stats']['played'] }}</div>
                <div class="text-sm text-gray-600">Matchs Joués</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $team['stats']['wins'] }}</div>
                <div class="text-sm text-gray-600">Victoires</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-red-600">{{ $team['stats']['losses'] }}</div>
                <div class="text-sm text-gray-600">Défaites</div>
            </div>
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-2xl font-bold text-club-blue">{{ $team['stats']['win_rate'] }}%</div>
                <div class="text-sm text-gray-600">Taux de Victoire</div>
            </div>
        </div>
    </div>
</div>
