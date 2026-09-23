@props([
    'candidates',
    'selected' => null,
    'property',
    'heading',
    'emptyMessage',
    'outgoing' => false,
])

{{--
Les candidates d'un rapprochement, entrantes ou sortantes.

Le filtre est en Alpine, pas en Livewire : la liste entière est déjà chargée et
déjà dans le DOM, et une propriété `.live` sur ce composant re-rendrait tout
l'écran — tableau, cartes et leurs requêtes — à chaque frappe.
--}}
<div x-data="{
    q: '',
    fold(s) { return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() },
    shows(el) { return this.q === '' || el.dataset.search.includes(this.fold(this.q)) },
}">
    <div class="text-xs font-bold uppercase tracking-widest text-muted mb-3">{{ $heading }}</div>

    <label class="flex items-center gap-2 rounded-xl bg-base-200 px-3 py-2 mb-3">
        <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
        {{-- Le label ne porte qu'une icône : sans aria-label le champ n'a aucun
        nom pour un lecteur d'écran. --}}
        <input
            x-model="q"
            data-candidate-filter
            type="search"
            aria-label="{{ __('Filter by name or communication…') }}"
            class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
            placeholder="{{ __('Filter by name or communication…') }}" />
    </label>

    <div class="space-y-2 max-h-96 overflow-y-auto pr-1" x-ref="list">
        @forelse($candidates as $transaction)
            @php
                $match = $transaction->match ?? null;
                $strength = $match?->strength?->value ?? 'none';
                // Plié sans accents ni casse : le barème ignore les accents, la
                // recherche doit faire pareil ou « felix » ne trouve pas « Félix ».
                $haystack = mb_strtolower(trim(collect([
                    $transaction->counterparty_name,
                    $transaction->counterparty_bank_account,
                    $transaction->structured_reference,
                    $transaction->free_reference,
                    $transaction->description,
                ])->filter()->implode(' ')));
                $haystack = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $haystack) ?: $haystack;
            @endphp

            <button type="button"
                wire:key="candidate-{{ $transaction->id }}"
                data-candidate
                data-search="{{ $haystack }}"
                x-show="shows($el)"
                wire:click="$set('{{ $property }}', {{ $transaction->id }})"
                aria-pressed="{{ $selected === $transaction->id ? 'true' : 'false' }}"
                @class([
                    'w-full text-left flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-150',
                    'border-primary bg-primary/5 shadow-sm' => $selected === $transaction->id,
                    'border-success/60 bg-success/5'        => $selected !== $transaction->id && $strength === 'strong',
                    'border-info/40 bg-info/5'              => $selected !== $transaction->id && $strength === 'to_verify',
                    'border-base-300 hover:border-primary bg-base-100' => $selected !== $transaction->id && ! in_array($strength, ['strong', 'to_verify'], true),
                ])>

                <div @class([
                    'w-4 h-4 mt-1 rounded-full border-2 shrink-0 flex items-center justify-center',
                    'border-primary bg-primary' => $selected === $transaction->id,
                    'border-base-300'           => $selected !== $transaction->id,
                ])>
                    @if($selected === $transaction->id)
                        <div class="w-2 h-2 rounded-full bg-primary-content"></div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    {{-- Le nom de contrepartie est la donnée qui distingue deux
                    virements du même montant : il passe à la ligne, il ne se
                    tronque pas. --}}
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-medium text-sm break-words">{{ $transaction->counterparty_name ?: '—' }}</span>
                        @if($strength !== 'none')
                            <span @class([
                                'shrink-0 text-xs font-bold uppercase tracking-wide px-1.5 py-0.5 rounded',
                                'text-success bg-success/15'          => $strength === 'strong',
                                'text-info bg-info/15'                => $strength === 'to_verify',
                                'text-warning-content bg-warning/15'  => $strength === 'weak',
                            ])>{{ $match->strength->getLabel() }}</span>
                        @endif
                    </div>

                    @if($match && $match->reasons !== [])
                        <div class="text-xs text-base-content/60 mt-0.5">{{ implode(' · ', $match->reasons) }}</div>
                    @endif

                    @if($transaction->structured_reference)
                        <div class="font-mono text-xs text-primary mt-0.5">{{ $transaction->structured_reference }}</div>
                    @elseif($transaction->free_reference)
                        <div class="text-xs text-muted mt-0.5 italic break-words">{{ $transaction->free_reference }}</div>
                    @endif

                    @if($outgoing && $transaction->counterparty_bank_account)
                        <div class="font-mono text-xs text-base-content/50 mt-0.5">{{ $transaction->counterparty_bank_account }}</div>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    <div @class(['font-bold tabular-nums', 'text-error' => $outgoing])>
                        {{ number_format($transaction->amount, 2, ',', ' ') }} €
                    </div>
                    @if (abs($transaction->residue()) > 0.001 && abs($transaction->residue()) < abs($transaction->amount))
                        {{-- Une ligne déjà entamée ressemblait trait pour trait à une
                             ligne intacte : le virement d'un parent pour deux enfants
                             affichait 120 € avant et après le premier rapprochement,
                             et on croyait que rien ne s'était produit. --}}
                        <div class="text-xs font-semibold tabular-nums text-info">
                            {{ __(':amount € left', ['amount' => number_format(abs($transaction->residue()), 2, ',', ' ')]) }}
                        </div>
                    @endif
                    <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</div>
                </div>
            </button>
        @empty
            <div class="flex flex-col items-center justify-center py-10 text-muted">
                <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
                <p class="text-sm italic">{{ $emptyMessage }}</p>
            </div>
        @endforelse

        {{-- Un filtre qui ne renvoie rien doit le dire : sans ça, la liste se
        vide en silence et l'écran ressemble à une absence de données. --}}
        <div x-show="q !== '' && ![...$refs.list.querySelectorAll('[data-candidate]')].some(el => shows(el))"
            x-cloak class="py-8 text-center text-sm italic text-muted">
            {{ __('No transaction matches this search.') }}
        </div>
    </div>
</div>
