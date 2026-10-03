<div class="pb-24">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Inventory')"
        :subtitle="__('Count what is on the shelf. Nothing touches the stock before you validate.')" />

    @if ($inventory === null)
        <x-card>
            <x-empty-state icon="o-clipboard-document-check" :heading="__('No inventory in progress')"
                :message="__('Start one to count the whole bar, or a single product after a breakage.')" />
            <div class="mt-4 flex justify-center">
                <x-button class="btn-primary" icon="o-plus" :label="__('Start an inventory')" wire:click="open" spinner="open" />
            </div>
        </x-card>
    @else
        <x-alert class="alert-info mb-4" icon="o-clipboard-document-check">
            <p class="font-semibold">{{ __('Opened by :name', ['name' => $inventory->opener->full_name]) }}</p>
            <p class="text-sm">{{ __('Since :date', ['date' => $inventory->opened_at->translatedFormat('l j F, H:i')]) }}</p>

            <x-slot:actions>
                <x-button class="btn-ghost btn-sm" :label="__('Cancel this inventory')" wire:click="$set('cancelModal', true)" />
            </x-slot:actions>
        </x-alert>

        <x-confirm-modal model="cancelModal" :title="__('Cancel this inventory?')"
            :confirmLabel="__('Cancel this inventory')" confirmAction="cancel" :open="$cancelModal">
            <div class="space-y-2">
                <p>{{ __('What was counted is dropped. The stock does not move.') }}</p>
                @if ($inventory->opened_by !== auth()->id())
                    <x-bar.restocking-contact :shopper="$inventory->opener" />
                @endif
            </div>
        </x-confirm-modal>

        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __('Show') }}">
            @foreach (['all' => __('All · :count', ['count' => $productCount]), 'uncounted' => __('Not counted · :count', ['count' => $productCount - $summary['counted']]), 'gaps' => __('With a gap · :count', ['count' => $summary['gaps'] + ($summary['added'] > 0 ? 1 : 0)])] as $value => $label)
                <button type="button" wire:click="$set('filter', '{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                    @class(['badge badge-lg cursor-pointer', 'badge-primary' => $filter === $value, 'badge-ghost' => $filter !== $value])>{{ $label }}</button>
            @endforeach
        </div>

        @error('causes')
            <x-alert class="alert-error mb-4" icon="o-exclamation-triangle" :title="$message" />
        @enderror
        @error('counts')
            <x-alert class="alert-error mb-4" icon="o-exclamation-triangle" :title="$message" />
        @enderror

        @forelse ($groups as $group)
            <section class="mb-4" wire:key="group-{{ $loop->index }}">
                <h2 class="text-subtle mb-1 px-1 text-xs font-bold tracking-wider uppercase">{{ $group['label'] }}</h2>
                <ul class="bg-base-100 border-base-300 divide-base-300 divide-y rounded-xl border">
                    @foreach ($group['products'] as $product)
                        @php
                            $line = $lines[$product->id] ?? null;
                            $gap = $line?->gap ?? 0;
                        @endphp
                        <li class="grid gap-2 px-4 py-3 lg:grid-cols-[minmax(0,1fr)_6rem_6rem_minmax(0,16rem)_minmax(0,14rem)] lg:items-center lg:gap-4"
                            wire:key="line-{{ $product->id }}" data-product="{{ $product->id }}">
                            <div class="flex items-center justify-between gap-3 lg:contents">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $product->name }}</p>
                                    <p class="text-subtle text-xs tabular-nums">
                                        @if ($line !== null)
                                            {{ __('Expected :count', ['count' => $line->expected]) }}
                                        @elseif (! $product->stock_in)
                                            {{ __('Never counted') }}
                                        @else
                                            {{ __('Expected :count', ['count' => $product->stock]) }}
                                        @endif
                                    </p>
                                </div>

                                {{-- `wire:change` et non `.live` : taper « 48 » par-dessus « 4 »
                                ne doit enregistrer qu'un seul comptage. --}}
                                <input type="number" min="0" inputmode="numeric" placeholder="—"
                                    wire:key="count-{{ $product->id }}"
                                    value="{{ $line?->counted }}"
                                    wire:change="saveCount({{ $product->id }}, $event.target.value)"
                                    aria-label="{{ __('Counted for :product', ['product' => $product->name]) }}"
                                    @class(['input input-bordered w-24 text-end text-lg font-semibold tabular-nums', 'input-primary' => $line !== null])>
                            </div>

                            <p @class(['hidden text-end tabular-nums lg:block', 'text-error font-semibold' => $gap < 0, 'text-success font-semibold' => $gap > 0, 'text-subtle' => $gap === 0])>
                                {{ $line === null ? '' : ($gap > 0 ? '+' . $gap : $gap) }}
                            </p>

                            @if ($line !== null && $gap !== 0)
                                @if ($line->cause === \App\Domains\Shared\Enums\BarInventoryCause::AddedToBar)
                                    <p class="lg:col-span-2"><span class="badge badge-info badge-soft">{{ $line->cause->label() }}</span></p>
                                @else
                                    <div class="bg-base-200 grid gap-2 rounded-lg p-2 lg:contents">
                                        <p @class(['text-sm font-semibold lg:hidden', 'text-error' => $gap < 0, 'text-success' => $gap > 0])>
                                            {{ __('Gap :gap', ['gap' => $gap > 0 ? '+' . $gap : $gap]) }}
                                        </p>
                                        <select class="select select-bordered select-sm w-full"
                                            wire:key="cause-{{ $product->id }}-{{ $gap < 0 ? 'short' : 'surplus' }}"
                                            wire:change="saveCause({{ $product->id }}, $event.target.value)"
                                            aria-label="{{ __('What happened to :product', ['product' => $product->name]) }}">
                                            <option value="" @selected($line->cause === null) disabled>{{ __('Choose what happened…') }}</option>
                                            @foreach ($gap < 0 ? $shortageCauses : $surplusCauses as $option)
                                                <option value="{{ $option['id'] }}" @selected($line->cause?->value === $option['id'])>{{ $option['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" maxlength="255" class="input input-bordered input-sm w-full"
                                            wire:key="note-{{ $product->id }}"
                                            value="{{ $line->note }}" placeholder="{{ __('Note (optional)') }}"
                                            wire:change="saveNote({{ $product->id }}, $event.target.value)"
                                            aria-label="{{ __('Note for :product', ['product' => $product->name]) }}">
                                    </div>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <x-card>
                <x-empty-state icon="o-check-circle" :heading="__('Nothing to show here.')" />
            </x-card>
        @endforelse

        {{-- La barre reste sous le pouce : combien de produits sont comptés, et Valider. --}}
        <div class="bg-base-100 border-base-300 fixed inset-x-0 bottom-0 z-20 border-t px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] lg:static lg:mt-4 lg:rounded-xl lg:border">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
                <p class="tabular-nums">
                    <span class="font-semibold">{{ $summary['counted'] }} / {{ $productCount }}</span>
                    <span class="text-subtle">{{ __('counted') }}</span>
                </p>
                <x-button class="btn-primary" :label="__('Validate…')" wire:click="$set('validateModal', true)" :disabled="$summary['counted'] === 0" />
            </div>
        </div>

        <x-app-modal wire:model="validateModal" :open="$validateModal" :title="__('Validate this inventory?')">
            <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
                <dt class="text-muted">{{ __('Products counted') }}</dt>
                <dd class="text-end font-semibold tabular-nums">{{ $summary['counted'] }} / {{ $productCount }}</dd>
                <dt class="text-muted">{{ __('Missing') }}</dt>
                <dd class="text-error text-end font-semibold tabular-nums">{{ $summary['missing'] > 0 ? '−' . $summary['missing'] : 0 }}</dd>
                <dt class="text-muted">{{ __('Surplus') }}</dt>
                <dd class="text-success text-end font-semibold tabular-nums">{{ $summary['surplus'] > 0 ? '+' . $summary['surplus'] : 0 }}</dd>
                <dt class="text-muted">{{ __('Value at the selling price') }}</dt>
                <dd class="text-end font-semibold tabular-nums">{{ euros($summary['value']) }}</dd>
                @if ($summary['added'] > 0)
                    <dt class="text-muted">{{ __('Added to the bar') }}</dt>
                    <dd class="text-end font-semibold tabular-nums">{{ $summary['added'] }}</dd>
                @endif
            </dl>

            @if ($summary['unexplained'] > 0)
                <x-alert class="alert-warning mt-3" icon="o-exclamation-triangle"
                    :title="trans_choice('Say what happened for :count product before validating.|Say what happened for :count products before validating.', $summary['unexplained'])" />
            @else
                <p class="text-muted mt-3 text-sm">{{ __('The stock of the counted products is aligned on your count. A summary goes to the treasurer and the store keepers. The inventory can no longer change: a mistake is fixed by a new inventory.') }}</p>
            @endif

            <x-textarea class="mt-3" :label="__('A word for the treasurer (optional)')" wire:model="comment" rows="2" maxlength="500" />

            <x-slot:actions>
                <x-button :label="__('Back to counting')" wire:click="$set('validateModal', false)" />
                <x-button class="btn-primary" :label="__('Validate and send')" wire:click="validateInventory" spinner="validateInventory" :disabled="$summary['unexplained'] > 0" />
            </x-slot:actions>
        </x-app-modal>
    @endif
</div>
