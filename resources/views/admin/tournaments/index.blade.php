<x-app-layout>

    
    <div class="container mx-auto px-4 py-8">
        <!-- En-tête du tournoi -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
                <h1 class="text-xl font-bold text-gray-700 mb-8">Tournaments</h1>
                
                
                <x-table 
                :headers="$headers"
                :rows="$rows" 
                />
            </div>
        </div>
    </div>
</x-app-layout>