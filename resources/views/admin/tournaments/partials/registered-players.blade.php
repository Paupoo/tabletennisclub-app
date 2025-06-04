<h2 class="text-xl font-bold text-gray-800 mb-6">Joueurs inscrits</h2>

<div class="overflow-x-auto mb-8">
    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                    #</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                    Joueur</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                    Classement</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                    Date d'inscription</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                    Paiement</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                    Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @if (count($tournament->users()->get()) > 0)
                @foreach ($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span
                                class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm">
                                {{ $loop->iteration }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-medium text-gray-900">{{ $user->first_name }}
                                {{ $user->last_name }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-900">{{ $user->ranking }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $user->pivot->updated_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $user->pivot->has_paid ? 'green' : 'red' }}-100 text-{{ $user->pivot->has_paid ? 'green' : 'red' }}-800">
                                @if ($user->pivot->has_paid)
                                    Payé
                                @else
                                    Paiement en attente
                                @endif
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>
                                <a href="{{ route('tournamentToggleHasPaid', [$tournament, $user]) }}">
                                    @if (!$user->pivot->has_paid)
                                        <!-- Marquer comme payé -->
                                        <button
                                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                            title="Marquer comme payé">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    @else
                                        <!-- Marquer comme impayé -->
                                        <button
                                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                            title="Marquer comme impayé">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    @endif
                                </a>

                                <!-- Désinscrire -->
                                <a href="{{ route('tournamentUnregister', [$tournament, $user]) }}">
                                    <button
                                        class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        title="Désinscrire">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 italic">{{ __('No registered players')}}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{ $users->links() }}
</div>
