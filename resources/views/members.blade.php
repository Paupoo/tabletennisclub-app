<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Members') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
            

                    <table class="min-w-full text-sm font-light text-left">
                        <thead class="font-medium border-b dark:border-neutral-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">#</th>
                                <th scope="col" class="px-4 py-2">{{ __('Index') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Last Name') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('First Name')}}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Ranking') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Team') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Phone Number') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Email Address') }}</th>
                            </tr>
                        </thead>
                        <tbody>

                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 font-medium whitespace-nowrap">
                                    1
                                </td>
                                <td class="px-4 whitespace-nowrap">1</td>
                                <td class="px-4 whitespace-nowrap">Docquier</td>
                                <td class="px-4 whitespace-nowrap">Augustin</td>
                                <td class="px-4 whitespace-nowrap">B6</td>
                                <td class="px-4 whitespace-nowrap">A</td>
                                <td class="px-4 whitespace-nowrap">0499353062</td>
                                <td class="px-4 whitespace-nowrap">augustin.docquier@gmail.com</td>
                            </tr>

                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
