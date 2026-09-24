@props(['mode' => 'amount'])

@can('subscriptions.discount')
    {{--
        Le raccourci : proposer une remise au moment où le club décide d'un prix,
        pour éviter de rouvrir la fiche juste après avoir validé. Le geste
        canonique vit sur l'affiliation et reste disponible à tout moment.

        Replié par défaut : la plupart des validations n'accordent rien, et un
        formulaire toujours ouvert inviterait à remplir ce qu'on n'a pas décidé.
        Même mécanique que le repli « motif de refus » juste en dessous — un
        `<details>` natif n'aurait ressemblé à rien d'autre dans cet écran.

        `mode` est passé en propriété : un composant Blade anonyme ne voit pas
        la portée du composant Livewire qui l'inclut, et seul le libellé du
        champ en dépend — les `wire:model`, eux, se lient par leur nom.
    --}}
    <div x-data="{ discountOpen: false }">
        <button type="button" @click="discountOpen = !discountOpen"
            class="flex items-center gap-1.5 text-xs text-success opacity-70 transition-opacity hover:opacity-100">
            <x-icon name="o-chevron-down" class="h-3.5 w-3.5 transition-transform" ::class="discountOpen ? '' : '-rotate-90'" />
            {{ __('Grant a discount') }}
        </button>

        <div x-show="discountOpen" x-collapse class="mt-3 space-y-3 rounded-xl border border-success/20 bg-success/5 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-select :label="__('Expressed as')" wire:model.live="inlineDiscountMode" :options="[
                    ['id' => 'amount', 'name' => __('An amount in €')],
                    ['id' => 'percent', 'name' => __('A percentage')],
                ]" option-value="id" option-label="name" />

                <x-input
                    :label="$mode === 'percent' ? __('Percentage (%)') : __('Amount (€)')"
                    type="number" step="0.01" min="0"
                    wire:model="inlineDiscountValue" />
            </div>

            <x-input :label="__('Reason')" wire:model="inlineDiscountReason"
                :placeholder="__('Thank you for a season behind the bar')"
                :hint="__('Mandatory as soon as an amount is entered — otherwise nothing is granted.')" />
        </div>
    </div>
@endcan
