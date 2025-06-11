<div>
    <h2 class="text-xl font-bold text-gray-800 mb-6">Joueurs inscrits</h2>

    <div class="overflow-x-auto mb-8">
        <x-table.container>
            <x-table.header>
                <x-table.row>
                    <x-table.header-cell
                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase x-table.rowacking-wider">
                        #</x-table.header-cell>
                    <x-table.header-cell
                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase x-table.rowacking-wider">
                        Joueur</x-table.header-cell>
                    <x-table.header-cell
                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase x-table.rowacking-wider">
                        Classement</x-table.header-cell>
                    <x-table.header-cell
                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase x-table.rowacking-wider">
                        Date d'inscription</x-table.header-cell>
                    @if($tournament->price > 0)
                    <x-table.header-cell
                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase x-table.rowacking-wider">
                        Paiement</x-table.header-cell>
                    @endif
                    <x-table.header-cell
                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase x-table.rowacking-wider">
                        Actions</x-table.header-cell>
                </x-table.row>
            </x-table.header>
            <tbody class="bg-white divide-y divide-gray-200">
                @if (count($tournament->users()->get()) > 0)
                    @foreach ($users as $user)
                        <x-table.row>
                            <x-table.cell>
                                <span class="text-sm text-gray-900">
                                    {{ $loop->iteration }}
                                </span>
                            </x-table.cell>
                            <x-table.cell>
                                <span class="text-sm text-gray-900">{{ $user->first_name }}
                                    {{ $user->last_name }}</span>
                            </x-table.cell>
                            <x-table.cell>
                                <span class="text-sm text-gray-900">{{ $user->ranking }}</span>
                            </x-table.cell>
                            <x-table.cell>
                                <span
                                    class="text-sm text-gray-900">{{ $user->pivot->updated_at->format('d/m/Y') }}</span>
                            </x-table.cell>
                            @if($tournament->price > 0)
                            <x-table.cell>
                                <span
                                    class="px-2 inline-flex text-xs font-sm leading-5 font-semibold rounded-full bg-{{ $user->pivot->has_paid ? 'green' : 'red' }}-100 text-{{ $user->pivot->has_paid ? 'green' : 'red' }}-800">
                                    @if ($user->pivot->has_paid)
                                        Payé
                                    @else
                                        Paiement en attente
                                    @endif
                                </span>
                            </x-table.cell>
                            @endif
                            <x-table.cell class="text-right">
                                <x-tournament.user-actions :tournament="$tournament" :user="$user" />
                            </x-table.cell>
                        </x-table.row>
                    @endforeach
                @else
                    <x-table.row>
                        <x-table.cell colspan="6" class="px-4 py-4 text-center text-gray-500 italic">
                            {{ __('No registered players') }}</x-table.cell>
                    </x-table.row>
                @endif
            </tbody>
        </x-table.container>

        {{ $users->links() }}
    </div>

</div>
