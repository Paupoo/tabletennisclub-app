<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('members.index') }}" method="GET">
                <x-primary-button>{{ __('Manage members') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>


    
    <div class="pt-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div>
                    {{ __('Last name:') }} {{ $member->last_name }}
                </div>
                <div>
                    {{ __('First name:') }} {{ $member->first_name }}
                </div>
                <div>
                    {{ __('Email:') }} {{ $member->email }}
                </div>
                <div>
                    {{ __('Phone number:') }} {{ $member->phone_number }}
                </div>
                <div>
                    {{ __('Street:') }} {{ $member->street }}
                </div>
                <div>
                    {{ __('City:') }} {{ $member->city }}
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
