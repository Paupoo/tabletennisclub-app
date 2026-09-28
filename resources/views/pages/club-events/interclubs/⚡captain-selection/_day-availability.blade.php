{{-- ── LA JOURNÉE VUE D'EN HAUT ─────────────────────────────────────────
     Pour le sélectionneur qui doit débloquer une équipe : qui a dit oui ou
     peut-être, à quel niveau, où il est déjà coché, et où il pourrait aller.
     Une catégorie par bloc — un indice de force n'a de sens que dans la sienne.
     Les pastilles d'équipe mènent au tiroir, qui porte seul les règles
     d'écriture : cette vue trouve la solution, le tiroir l'applique. --}}
@php
    $needClasses = [
        'complete' => 'border-success/40 bg-success/10 text-success',
        'coverable' => 'border-warning/50 bg-warning/10 text-warning-content',
        'uncovered' => 'border-error/40 bg-error/10 text-error',
    ];
@endphp

<div data-day-availability class="space-y-6">
    <div class="flex flex-col gap-3">
        <h2 class="text-lg font-bold">
            {{ __('Match day') }} {{ $selectedMatchDay === null ? '—' : ($matchDayMap[$selectedMatchDay] ?? $selectedMatchDay) }}
        </h2>
        @include('pages::club-events.interclubs.⚡captain-selection._day-picker')
    </div>

    @forelse ($dayAvailability as $day)
        <section wire:key="day-availability-{{ $selectedMatchDay }}-{{ $day->category?->name ?? 'none' }}"
            class="rounded-xl border border-base-300 p-3 sm:p-4">

            <div class="mb-3 flex flex-wrap items-center gap-2">
                @if ($day->category)
                    <span class="{{ $day->category->badgeClasses() }} badge font-bold">{{ $day->category->label() }}</span>
                @endif

                {{-- Ce qu'il manque à chaque équipe, et la porte vers sa compo. --}}
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 py-0.5">
                    @foreach ($day->teams as $team)
                        @php
                            $opensLineup = $mayComposeFixture[$team->fixtureId] ?? false;
                        @endphp
                        <button type="button"
                            wire:click="{{ $opensLineup ? 'openSelection' : 'inspectSelection' }}({{ $team->fixtureId }})"
                            @if ($team->isShortHanded) title="{{ __('Declared short-handed') }}" @endif
                            @class([
                                'shrink-0 cursor-pointer rounded-full border px-3 py-1 text-sm font-bold tabular-nums transition hover:ring-2 hover:ring-base-content/10',
                                $needClasses[$team->need->value],
                                'ring-2 ring-warning' => $team->isShortHanded,
                            ])>
                            {{ $team->teamName }}
                            <span class="font-semibold">{{ $team->selectedCount }}/{{ $team->totalPlayers }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($day->players->isEmpty())
                <p class="text-sm text-base-content/70">{{ __('Nobody said yes or maybe for this match day.') }}</p>
            @else
                {{-- Téléphone : deux niveaux, le nom garde la largeur. --}}
                <ul class="divide-y divide-base-200 lg:hidden">
                    @foreach ($day->players as $row)
                        <li wire:key="day-row-m-{{ $row->user->id }}"
                            @class(['flex items-start gap-3 py-2', 'opacity-70' => $row->availability === \App\Domains\Shared\Enums\InterclubAvailability::MAYBE])>
                            <span class="w-8 shrink-0 text-right text-sm font-bold tabular-nums text-base-content/70">{{ $row->forceIndex }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-bold">
                                    {{ $row->user->last_name }} {{ $row->user->first_name }}
                                    <span class="font-semibold text-base-content/70">· {{ $row->user->ranking->getLabel() }}</span>
                                </div>
                                <div class="text-xs text-base-content/70">
                                    {{ $row->teamName }} ·
                                    @if ($row->lineupTeamName !== null)
                                        {{ __('Lined up') }}
                                    @endif
                                    @include('pages::club-events.interclubs.⚡captain-selection._day-availability-lineup', ['row' => $row])
                                    @if (! $dayIsPlayed && $row->canHelp !== [])
                                        · {{ __('Can fill in') }}
                                        @include('pages::club-events.interclubs.⚡captain-selection._day-availability-help', ['row' => $row])
                                    @endif
                                </div>
                            </div>
                            <span class="{{ $row->availability->color() }} badge badge-sm shrink-0 font-bold">{{ $row->availability->label() }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- Un bloc qui porte l'affichage plutôt que `lg:table` : `table` est
                     aussi le composant daisyUI, et la variante perdait son
                     `display` selon la façon dont le CSS était généré. --}}
                <div class="hidden lg:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-base-300 text-left text-xs font-semibold uppercase tracking-wide text-base-content/70">
                                <th class="w-12 py-2 pr-3 text-right">#</th>
                                <th class="py-2 pr-3">{{ __('Player') }}</th>
                                <th class="py-2 pr-3">{{ __('Ranking') }}</th>
                                <th class="py-2 pr-3">{{ __('Team') }}</th>
                                <th class="py-2 pr-3">{{ __('Answer') }}</th>
                                <th class="py-2 pr-3">{{ __('Lined up') }}</th>
                                @unless ($dayIsPlayed)
                                    <th class="py-2">{{ __('Can fill in') }}</th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            @foreach ($day->players as $row)
                                <tr wire:key="day-row-{{ $row->user->id }}"
                                    @class(['opacity-70' => $row->availability === \App\Domains\Shared\Enums\InterclubAvailability::MAYBE])>
                                    <td class="py-1.5 pr-3 text-right font-bold tabular-nums text-base-content/70">{{ $row->forceIndex }}</td>
                                    <td class="py-1.5 pr-3 font-bold">{{ $row->user->last_name }} {{ $row->user->first_name }}</td>
                                    <td class="py-1.5 pr-3 tabular-nums">{{ $row->user->ranking->getLabel() }}</td>
                                    <td class="py-1.5 pr-3">{{ $row->teamName }}</td>
                                    <td class="py-1.5 pr-3">
                                        <span class="{{ $row->availability->color() }} badge badge-sm font-bold">{{ $row->availability->label() }}</span>
                                    </td>
                                    <td class="py-1.5 pr-3">
                                        @include('pages::club-events.interclubs.⚡captain-selection._day-availability-lineup', ['row' => $row])
                                    </td>
                                    @unless ($dayIsPlayed)
                                        <td class="py-1.5">
                                            @if ($row->canHelp === [])
                                                <span class="text-base-content/50">—</span>
                                            @else
                                                @include('pages::club-events.interclubs.⚡captain-selection._day-availability-help', ['row' => $row])
                                            @endif
                                        </td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($day->silentCount > 0 || $day->unrankedCount > 0)
                <p class="mt-3 text-xs text-base-content/70">
                    @if ($day->silentCount > 0)
                        {{ trans_choice(':count member has not answered|:count members have not answered', $day->silentCount, ['count' => $day->silentCount]) }}
                    @endif
                    @if ($day->silentCount > 0 && $day->unrankedCount > 0)
                        ·
                    @endif
                    @if ($day->unrankedCount > 0)
                        {{ trans_choice(':count available player has no force index and cannot be lined up|:count available players have no force index and cannot be lined up', $day->unrankedCount, ['count' => $day->unrankedCount]) }}
                    @endif
                </p>
            @endif
        </section>
    @empty
        <x-empty-state icon="o-calendar" :heading="__('No matches scheduled.')" />
    @endforelse
</div>
<span data-day-availability-end hidden></span>
