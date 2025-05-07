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
        @if ($match->isInProgress())
        <a href="{{ route('editMatch', $match) }}"
            class="text-indigo-600 hover:text-indigo-900 mr-3">
            {{ $match->isCompleted() ? 'Modifier' : 'Saisir le score' }}
        </a>
    @elseif ($match->isCompleted())
        <form action="{{ route('resetMatch', $match) }}" method="POST"
            class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 hover:text-red-900"
                onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser ce match ?')">
                Réinitialiser
            </button>
        </form>
    @elseif($tables->count() == 0)
        <a href="{{ route('tablesOverview', $tournament) }}">
            <p class="text-gray-600">
                {{ __('All the tables are currently used') }}
            </p>
        </a>
    @else
        <div
            class="flex items-center p-2 bg-gray-50 rounded-lg shadow-sm hover:bg-gray-100 transition">
            <form action="{{ route('startMatch', $match) }}"
                method="POST" class="flex w-full items-center space-x-2">
                @csrf
                <div class="flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-indigo-500"
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"
                            clip-rule="evenodd" />
                    </svg>
                    <label for="table_id"
                        class="text-sm font-medium text-gray-700">Table</label>
                </div>

                <select name="table_id" id="table_id"
                    class="pl-3 pr-10 py-1 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                    <option selected value="" disabled>
                        {{ __('Select a table') }}</option>
                    @foreach ($tables as $table)
                        @if($table->pivot->is_table_free)
                        <option value="{{ $table->id }}">
                            {{ $table->name }}
                            ==>
                            {{ $table->room->name }}</option>
                        @endif
                    @endforeach
                </select>

                <button type="submit"
                    class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    <span>Démarrer</span>
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="ml-1 h-4 w-4" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </form>
        </div>
    @endif
        
    </div>
</div>