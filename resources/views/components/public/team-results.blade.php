@props(['team'])

<div class="mb-12">
    <div class="bg-base-100 rounded-lg shadow-xs border border-base-300 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-primary">{{ $team['name'] }}</h3>
            <div class="{{ $team['position_class'] ?? 'bg-green-100 text-green-800' }} px-3 py-1 rounded-full text-sm font-medium text-center">
                {{ $team['position'] }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-base-300">
                        <th class="text-left py-3 px-4 font-semibold">Date</th>
                        <th class="text-left py-3 px-4 font-semibold">Adversaire</th>
                        <th class="text-left py-3 px-4 font-semibold hidden md:block">{{ __('Home/Away') }}</th>
                        <th class="text-left py-3 px-4 font-semibold">Score</th>
                        <th class="text-left py-3 px-4 font-semibold hidden md:block">{{ __('Result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($team['matches'] as $match)
                        <tr class="border-b border-base-300 hover:bg-base-200">
                            <td class="py-3 px-4 hidden md:block">{{ $match['date'] }}</td>
                            <td class="py-3 px-4 block md:hidden">13-12-24</td>
                            <td class="py-3 px-4">{{ $match['opponent'] }}</td>
                            <td class="py-3 px-4 hidden md:block">{{ $match['venue'] }}</td>
                            <td class="py-3 px-4 font-mono ">{{ $match['score'] }}</td>
                            <td class="py-3 px-4 hidden md:block">
                                <span @class([
                                    'px-2 py-1 rounded-sm text-sm font-medium',
                                    'bg-green-100 text-green-800' => in_array($match['result'], ['Victoire', 'Forfait Adverse']),
                                    'bg-red-100 text-red-800'    => in_array($match['result'], ['Défaite', 'Forfait']),
                                    'bg-orange-100 text-orange-700' => in_array($match['result'], ['Forfait Général', 'Forfait Général Adverse']),
                                    'bg-base-200 text-base-content'  => ! in_array($match['result'], ['Victoire', 'Forfait Adverse', 'Défaite', 'Forfait', 'Forfait Général', 'Forfait Général Adverse']),
                                ])>
                                    {{ $match['result'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-base-200 rounded-lg">
                <div class="text-2xl font-bold text-primary">{{ $team['stats']['played'] }}</div>
                <div class="text-sm text-muted">{{ __('Matches Played') }}</div>
            </div>
            <div class="text-center p-3 bg-base-200 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $team['stats']['wins'] }}</div>
                <div class="text-sm text-muted">Victoires</div>
            </div>
            <div class="text-center p-3 bg-base-200 rounded-lg">
                <div class="text-2xl font-bold text-red-600">{{ $team['stats']['losses'] }}</div>
                <div class="text-sm text-muted">{{ __('Losses') }}</div>
            </div>
            <div class="text-center p-3 bg-base-200 rounded-lg">
                <div class="text-2xl font-bold text-primary">{{ $team['stats']['win_rate'] }}%</div>
                <div class="text-sm text-muted">Taux de Victoire</div>
            </div>
        </div>
    </div>
</div>
