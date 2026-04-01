<x-tab name="5" label="{{ __('Recap') }}" icon="o-flag">

    <div class="max-w-5xl mx-auto mt-10 space-y-10 animate-in fade-in">

        {{-- Header --}}
        <div class="text-center bg-base-200/60 backdrop-blur rounded-2xl p-10 border border-base-300">

            <div
                class="w-20 h-20 mx-auto flex items-center justify-center rounded-full bg-warning/10 mb-6">
                <x-icon name="o-sparkles" class="w-10 h-10 text-warning" />
            </div>

            <h2 class="text-3xl font-bold">
                {{ __('Tournament closed') }}
            </h2>

            <p class="text-base-content/70 mt-2">
                {{ __('Everything is completed. You can now export and share results.') }}
            </p>

        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">

            <x-stat title="{{ __('Invitation Response Rate') }}" value="45%" icon="o-users"
                color="text-primary" class="" />

            <x-stat title="{{ __('Registrations') }}" value="32" icon="o-pencil-square"
                color="text-primary">

                <x-slot:description>
                    <span class="text-error">
                        3 {{ __('Abs.') }}
                    </span>
                    •
                    <span class="text-warning">
                        5 {{ __('Last min. reg.') }}
                    </span>
                </x-slot:description>

            </x-stat>

            <x-stat title="Matches played" value="63" icon="o-numbered-list" color="text-primary"
                class="">
                <x-slot:description>
                    <span>
                        {{ __('Min') }} 3 {{ __('matches per player') }}
                    </span>
                </x-slot:description>
            </x-stat>

            <x-stat title="Cash in" value="489" icon="o-banknotes" color="text-success"
                class="">
                <x-slot:description>
                    <x-icon name="o-ticket" /> 320&nbsp;€ • <x-icon name="o-shopping-cart" /> 169&nbsp;€

                </x-slot:description>
            </x-stat>

        </div>

        {{-- Actions --}}
        <div class="bg-base-200/60 border border-base-300 rounded-2xl p-8">

            <h3 class="font-semibold text-lg mb-6 text-center">
                {{ __('Next actions') }}
            </h3>

            <div class="flex flex-col md:flex-row justify-center gap-4">

                <x-button label="{{ __('Send email to participants') }}" icon="o-envelope-open"
                    class="btn-primary" />

                <x-button label="{{ __('Write an article') }}" icon="o-pencil-square"
                    class="btn-outline" />

                <x-button label="{{ __('Print report (PDF)') }}" icon="o-document-chart-bar"
                    class="btn-ghost" />
            </div>

        </div>

    </div>

</x-tab>