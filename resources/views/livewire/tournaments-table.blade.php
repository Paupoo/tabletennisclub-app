<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    
    <!-- En-tête de page -->
    <x-layout.page-header 
        title="Gestion des Tournois"
        description="Gérez vos tournois et leur statut"
    />

    <!-- Barre de filtres -->
        <div class="flex flex-row justify-start mb-6">
            <x-forms.search-input
                placeholder="Rechercher un tournoi..."
                wire:model.live.debounce.500ms="search"
            />
            
            <div class="flex items-center gap-3 ml-auto">
                <x-forms.select-input wire:model.live="status">
                    <option value="">Tous les statuts</option>
                    <option value="draft">Brouillon</option>
                    <option value="open">Ouvert</option>
                    <option value="pending">En cours</option>
                    <option value="closed">Fermé</option>
                </x-forms.select-input>
            
                <x-forms.select-input wire:model.live="perPage">
                    <option value="10">10 par page</option>
                    <option value="20">20 par page</option>
                    <option value="50">50 par page</option>
                    <option value="0">Tous</option>
                </x-forms.select-input>
            </div>
        </div>

    <!-- Tableau des tournois -->
    <x-table.container>
        <x-table.header>
            <x-table.header-cell>#</x-table.header-cell>
            <x-table.header-cell>{{ __('Name') }}</x-table.header-cell>
            <x-table.header-cell>{{ __('Start Date') }}</x-table.header-cell>
            <x-table.header-cell>{{ __('Price') }}</x-table.header-cell>
            <x-table.header-cell>{{ __('Players') }}</x-table.header-cell>
            <x-table.header-cell>{{ __('Status') }}</x-table.header-cell>
            <x-table.header-cell class="text-right">{{ __('Actions') }}</x-table.header-cell>
        </x-table.header>

        <x-table.body>
            @forelse($tournaments as $tournament)
                <x-table.row>
                    <x-table.cell>
                        <span class="text-sm text-gray-900">{{ $loop->iteration }}</span>
                    </x-table.cell>
                    
                    <x-table.cell>
                        <div class="text-sm font-medium text-gray-900">
                            <a href="{{ route('tournamentShow', $tournament) }}" 
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $tournament->name }}
                            </a>
                        </div>
                    </x-table.cell>
                    
                    <x-table.cell>
                        <span class="text-sm text-gray-900">
                            {{ $tournament->start_date->format('d M Y à H:i') }}
                        </span>
                    </x-table.cell>
                    
                    <x-table.cell>
                        <span class="text-sm text-gray-900">{{ $tournament->price }} €</span>
                    </x-table.cell>
                    
                    <x-table.cell>
                        <span class="text-sm text-gray-500">
                            {{ $tournament->total_users }} / {{ $tournament->max_users }}
                        </span>
                    </x-table.cell>
                    
                    <x-table.cell>
                        <x-tournament.status-badge :status="$tournament->status" />
                    </x-table.cell>
                    
                    <x-table.cell class="text-right">
                        <x-tournament.status-actions :tournament="$tournament" />
                    </x-table.cell>
                </x-table.row>
            @empty
                <x-table.row>
                    <x-table.cell colspan="7" class="text-center text-gray-500 italic">
                        {{ __('No tournament found.') }}
                    </x-table.cell>
                </x-table.row>
            @endforelse
        </x-table.body>
        
        <x-slot name="pagination">
            {{ $tournaments->links() }}
        </x-slot>
    </x-table.container>

    <!-- Légende -->
    <x-ui.legend>
        <x-ui.legend-item icon="draft" icon-class="text-gray-400">
            Mettre en brouillon
        </x-ui.legend-item>
        
        <x-ui.legend-item icon="publish" icon-class="text-blue-600">
            Publier
        </x-ui.legend-item>
        
        <x-ui.legend-item icon="edit" icon-class="text-indigo-600">
            Modifier
        </x-ui.legend-item>
        
        <x-ui.legend-item icon="delete" icon-class="text-red-600">
            Supprimer
        </x-ui.legend-item>
    </x-ui.legend>
</div>