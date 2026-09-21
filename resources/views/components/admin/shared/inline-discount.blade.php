@props(['mode' => 'amount'])

@can('subscriptions.discount')
    {{--
        Le raccourci : proposer une remise au moment où le club décide d'un prix,
        pour éviter de rouvrir la fiche juste après avoir validé. Le geste
        canonique vit sur l'affiliation et reste disponible à tout moment.

        Replié par défaut : la plupart des validations n'accordent rien, et un
        formulaire toujours ouvert inviterait à remplir ce qu'on n'a pas décidé.

        `mode` est passé en propriété : un composant Blade anonyme ne voit pas
        la portée du composant Livewire qui l'inclut, et seul le libellé du
        champ en dépend — les `wire:model`, eux, se lient par leur nom.
    --}}
    <details class="rounded-xl border border-base-300 bg-base-200/40 p-3">
        <summary class="cursor-pointer text-xs font-bold uppercase tracking-widest text-muted">
            {{ __('Grant a discount') }}
        </summary>

        <div class="mt-3 space-y-3">
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
    </details>
@endcan
