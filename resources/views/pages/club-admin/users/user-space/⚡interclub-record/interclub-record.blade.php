<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator :title="__('My interclub record')"
        :subtitle="__('Every individual match the federation has you on')" separator>
        <x-slot:actions>
            @if ($seasons->isNotEmpty())
                <x-select wire:model.live="seasonId" class="select-sm w-48"
                    :options="$seasons->map(fn ($season) => ['id' => $season->id, 'name' => $season->name])"
                    option-value="id" option-label="name" :placeholder="__('All seasons')"
                    placeholder-value="0" />
            @endif
        </x-slot:actions>
    </x-header>

    @if ($totals['played'] === 0)
        <x-empty-state icon="o-trophy" :heading="__('No interclub match recorded yet')"
            :message="__('Individual results appear here once the federation has published the match sheet.')" />
    @else
        {{-- Le total, d'abord : c'est la raison d'ouvrir la page. --}}
        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                ['value' => $totals['played'], 'label' => __('Matches played')],
                ['value' => $totals['won'], 'label' => __('Matches won')],
                ['value' => $totals['rate'] . '%', 'label' => __('Win rate')],
                ['value' => $totals['ties'], 'label' => __('Ties')],
            ] as $stat)
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4 text-center">
                    <div class="text-2xl font-bold text-base-content sm:text-3xl">{{ $stat['value'] }}</div>
                    <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                        {{ $stat['label'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-10">
            @foreach ($bySeason as $seasonName => $ties)
                <section wire:key="season-{{ $seasonName }}">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="text-sm font-bold uppercase tracking-wide">{{ $seasonName }}</span>
                        <span class="text-xs text-base-content/40">
                            {{ trans_choice(':count tie|:count ties', $ties->count()) }}
                        </span>
                        <div class="flex-1 border-t border-base-300"></div>
                    </div>

                    <div class="space-y-4">
                        @foreach ($ties as $tie)
                            <div class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
                                wire:key="tie-{{ $tie['id'] }}">
                                <a href="{{ route('admin.interclubs.my-match', $tie['id']) }}"
                                    class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-base-300 px-4 py-3 transition-colors hover:bg-base-200/50">
                                    <span class="text-sm font-bold">vs {{ $tie['opponent'] }}</span>
                                    @if ($tie['is_home'])
                                        <x-badge class="badge-neutral badge-xs font-bold" :value="__('Home')" />
                                    @else
                                        <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" :value="__('Away')" />
                                    @endif
                                    <span class="text-xs text-base-content/50">
                                        {{ $tie['date']->translatedFormat('D d/m/Y') }} · {{ $tie['team'] }}
                                    </span>
                                    <span class="ml-auto whitespace-nowrap text-sm font-bold tabular-nums">
                                        {{ $tie['won'] }}<span class="font-normal text-base-content/40">/{{ $tie['lines']->count() }}</span>
                                    </span>
                                    <x-icon name="o-chevron-right" class="h-4 w-4 shrink-0 opacity-30" />
                                </a>

                                <div class="divide-y divide-base-200">
                                    @foreach ($tie['lines'] as $line)
                                        <div class="flex items-center gap-3 px-4 py-2 text-sm" wire:key="line-{{ $line->id }}">
                                            <span class="w-6 shrink-0 text-xs tabular-nums opacity-40">{{ $line->position }}</span>
                                            <span class="min-w-0 flex-1 truncate">
                                                {{ $line->opponent_name ?? '—' }}
                                                @if ($line->opponent_ranking)
                                                    <span class="opacity-50">({{ $line->opponent_ranking }})</span>
                                                @endif
                                            </span>
                                            <span class="shrink-0 whitespace-nowrap tabular-nums">
                                                @if ($line->is_forfeit)
                                                    <span class="text-xs italic opacity-60">{{ __('Forfeit') }}</span>
                                                @else
                                                    {{ $line->setScore() ?? '—' }}
                                                @endif
                                            </span>
                                            <x-icon :name="$line->we_won ? 'o-check' : 'o-x-mark'" @class([
                                                'h-4 w-4 shrink-0',
                                                'text-success' => $line->we_won,
                                                'text-error' => ! $line->we_won,
                                            ]) />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
