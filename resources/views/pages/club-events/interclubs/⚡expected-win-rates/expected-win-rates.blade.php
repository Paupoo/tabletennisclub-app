<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Expected wins')"
        :subtitle="__('How often a player is expected to win, by ranking gap. Every score is measured against this table.')" />

    <x-card class="max-w-xl">
        <x-form wire:submit="save">
            <table class="table-sm table">
                <thead>
                    <tr>
                        <th>{{ __('Opponent') }}</th>
                        <th class="w-32 text-end">{{ __('Expected wins') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gaps as $gap)
                        <tr wire:key="gap-{{ $gap }}">
                            <td>
                                @if ($gap === 0)
                                    {{ __('Same ranking') }}
                                @elseif ($gap > 0)
                                    {{ trans_choice(abs($gap) === 4 ? ':count or more crans stronger' : '{1} :count cran stronger|[2,*] :count crans stronger', abs($gap), ['count' => abs($gap)]) }}
                                @else
                                    {{ trans_choice(abs($gap) === 4 ? ':count or more crans weaker' : '{1} :count cran weaker|[2,*] :count crans weaker', abs($gap), ['count' => abs($gap)]) }}
                                @endif
                            </td>
                            <td>
                                <x-input type="number" min="0" max="100" suffix="%" wire:model="rates.{{ $gap }}" class="input-sm text-end"
                                    :aria-label="__('Expected wins')" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="text-subtle text-xs">
                {{ __('One cran is one step on the federation scale: B0, B2… E6, then NC. A gap is always read off the match sheet, from the two rankings it prints.') }}
            </p>

            <x-slot:actions>
                <x-button :label="__('Back')" link="{{ route('admin.interclubs.player-stats') }}" class="btn-ghost" />
                <x-button type="submit" :label="__('Save')" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
