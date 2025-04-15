<!-- resources/views/tournament/partials/knockout-match.blade.php -->
<div class="knockout-match">
    <div class="text-sm font-medium text-gray-500 mb-1">
        Match #{{ $match->match_number }}
        @if($match->table_number)
            <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded">Table {{ $match->table_number }}</span>
        @endif
    </div>

    <div class="flex flex-col space-y-2">
        <!-- Player 1 -->
        <div class="flex justify-between items-center {{ $match->winner_id == $match->player1_id ? 'bg-green-100' : '' }} p-1 rounded">
            <div class="flex-grow">
                @if($match->player1_id)
                    {{ $match->player1->first_name }} {{ $match->player1->last_name }}
                @else
                    <span class="text-gray-400 italic">En attente...</span>
                @endif
            </div>
            <div class="text-right font-medium">
                @if($match->status == 'completed')
                    {{ $match->sets->where('winner_id', $match->player1_id)->count() }}
                @endif
            </div>
        </div>

        <!-- Player 2 -->
        <div class="flex justify-between items-center {{ $match->winner_id == $match->player2_id ? 'bg-green-100' : '' }} p-1 rounded">
            <div class="flex-grow">
                @if($match->player2_id)
                    {{ $match->player2->first_name }} {{ $match->player2->last_name }}
                @else
                    <span class="text-gray-400 italic">En attente...</span>
                @endif
            </div>
            <div class="text-right font-medium">
                @if($match->status == 'completed')
                    {{ $match->sets->where('winner_id', $match->player2_id)->count() }}
                @endif
            </div>
        </div>
    </div>

    <!-- Match controls -->
    <div class="mt-2 flex justify-between text-xs">
        @if($match->status == 'scheduled' && $match->player1_id && $match->player2_id)
            <form action="{{ route('startKnockoutMatch', $match) }}" method="GET" class="inline">
                @csrf
                <div class="flex items-center">
                    <select name="tableNumber" class="mr-1 text-xs p-1 border border-gray-300 rounded">
                        @for($i = 1; $i <= 8; $i++)
                            <option value="{{ $i }}">T{{ $i }}</option>
                        @endfor
                    </select>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white py-1 px-2 rounded text-xs">
                        Commencer
                    </button>
                </div>
            </form>
        @elseif($match->status == 'in_progress')
            <a href="{{ route('editMatch', $match) }}" class="bg-green-500 hover:bg-green-700 text-white py-1 px-3 rounded text-xs">
                Saisir résultat
            </a>
        @elseif($match->status == 'completed')
            <form action="{{ route('resetKnockoutMatch', $match) }}" method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser ce match?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-orange-500 hover:bg-orange-700 text-white py-1 px-3 rounded text-xs">
                    Réinitialiser
                </button>
            </form>
            <a href="{{ route('editMatch', $match) }}" class="bg-gray-500 hover:bg-gray-700 text-white py-1 px-3 rounded text-xs">
                Détails
            </a>
        @endif
    </div>
</div>