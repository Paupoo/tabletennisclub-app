@props(['amountBeforeDiscounts', 'discounts'])

{{--
    Le prix normal et ce qui l'a allégé, sous le montant d'une communication.

    Discret par intention : le montant à virer reste la seule chose à lire
    d'un coup d'œil ; ces lignes répondent à « pourquoi pas le prix affiché ? »
    pour qui se pose la question. `discounts` est une liste de
    `['amount' => float, 'reason' => string]` — tableau ou modèles, la fenêtre
    admin passe l'un, l'espace membre l'autre.
--}}
@if (count($discounts) > 0)
    <div {{ $attributes->class('space-y-0.5 text-xs text-muted') }}>
        <div class="flex justify-between gap-3">
            <span>{{ __('Normal price') }}</span>
            <span class="tabular-nums line-through">{{ number_format($amountBeforeDiscounts, 2, ',', ' ') }} €</span>
        </div>
        @foreach ($discounts as $discount)
            <div class="flex justify-between gap-3">
                <span class="min-w-0 italic">{{ $discount['reason'] }}</span>
                <span class="whitespace-nowrap tabular-nums text-success">− {{ number_format($discount['amount'], 2, ',', ' ') }} €</span>
            </div>
        @endforeach
    </div>
@endif
