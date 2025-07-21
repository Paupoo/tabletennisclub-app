<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Rooms') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    @csrf
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                @can('create', \App\Models\Room::class)        
                <form action="{{ route('rooms.create') }}">
                    <x-primary-button>{{ __('Create a new room') }}</x-primary-button>
                </form>
                @endcan

            </div>
            
            @if(session('success'))
            <div class="mt-4 bg-green-500 rounded-lg">
                {{ session('success') }}
            </div>
            @elseif(session('deleted'))
            <div class="pl-3 mt-4 bg-red-500 rounded-lg">
                {{ session('deleted') }}
            </div>
            @endif
        </div>
    </div>

    <livewire:admin.rooms.rooms-index room={{ $room }}/>

</x-app-layout>
