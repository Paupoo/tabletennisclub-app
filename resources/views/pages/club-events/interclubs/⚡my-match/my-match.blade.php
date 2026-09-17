<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

@php
    $address = $interclub->room?->address ?: $interclub->address;
    $mapsUrl = $address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address) : null;

    /*
     * One band, seven cases. A played fixture speaks to everyone about the
     * result; an upcoming one speaks to the roster about themselves, and says
     * nothing at all to a délégation reading over their shoulder.
     */
    $bandState = match (true) {
        $isPast && $result?->result === null => 'awaiting',
        $isPast => 'result',
        ! $isOnRoster => null,
        $lineupPublished && $isSelected => 'selected',
        $lineupPublished => 'not-selected',
        $myAvailability !== null => 'answered',
        default => 'no-answer',
    };

    /*
     * The tint and the icon carry the verdict; the words stay in `base-content`.
     * A heading set in `text-warning` or `text-success` is the shortest road to
     * a contrast failure in one of the two themes (DS-B), and this is the one
     * line on the page nobody may have to squint at.
     */
    [$bandTint, $bandIcon, $bandIconTone] = match ($bandState) {
        'no-answer' => ['bg-warning/10 border-warning/30', 'o-question-mark-circle', 'text-warning'],
        'answered' => match ($myAvailability?->value) {
            'available' => ['bg-success/10 border-success/30', 'o-check-circle', 'text-success'],
            'unavailable' => ['bg-error/10 border-error/30', 'o-x-circle', 'text-error'],
            default => ['bg-warning/10 border-warning/30', 'o-question-mark-circle', 'text-warning'],
        },
        'selected' => ['bg-success/10 border-success/30', 'o-check-badge', 'text-success'],
        'not-selected' => ['bg-base-200/60 border-base-300', 'o-user-minus', 'text-base-content/40'],
        'awaiting' => ['bg-base-200/60 border-base-300', 'o-clock', 'text-base-content/40'],
        'result' => match ($result?->result?->value) {
            'Win', 'ForfeitWin', 'WithdrawalOpponent' => ['bg-success/10 border-success/30', 'o-trophy', 'text-success'],
            'Draw' => ['bg-base-200/60 border-base-300', 'o-equals', 'text-base-content/40'],
            default => ['bg-error/10 border-error/30', 'o-flag', 'text-error'],
        },
        default => ['bg-base-200/60 border-base-300', 'o-information-circle', 'text-base-content/40'],
    };
@endphp

<div>
    <x-header progress-indicator :title="__('My match')" separator />

    {{-- ── Bandeau de statut : la réponse à la question qui a fait cliquer ── --}}
    @if ($bandState)
        <div class="mb-8 flex items-start gap-4 rounded-2xl border {{ $bandTint }} p-5">
            <x-icon :name="$bandIcon" class="mt-0.5 h-7 w-7 shrink-0 {{ $bandIconTone }}" />

            <div class="min-w-0 flex-1">
                @if ($bandState === 'no-answer')
                    <p class="text-lg font-bold leading-tight text-base-content">
                        {{ __('Please tell us if you are available') }}
                    </p>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ __('Your captain is waiting for your answer to compose the team.') }}
                    </p>
                @elseif ($bandState === 'answered')
                    <p class="text-lg font-bold leading-tight text-base-content">
                        {{ __('You answered: :answer', ['answer' => $myAvailability->label()]) }}
                    </p>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ __('You can still change it until the line-up is published.') }}
                    </p>
                @elseif ($bandState === 'selected')
                    <p class="text-lg font-bold leading-tight text-base-content">
                        {{ __('You are playing on :day', ['day' => $interclub->start_date_time->translatedFormat('l j F')]) }}
                    </p>
                    <p class="mt-1 text-sm text-base-content/70">
                        @if ($captain && $selectedAt)
                            {{ __('Selected by :captain on :date', [
                                'captain' => $captain->full_name,
                                'date' => $selectedAt->translatedFormat('j F'),
                            ]) }}
                        @else
                            {{ __('You are in the published line-up.') }}
                        @endif
                    </p>
                @elseif ($bandState === 'not-selected')
                    <p class="text-lg font-bold leading-tight text-base-content">
                        {{ __('You are not in the line-up for this match') }}
                    </p>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ __('The captain has published the team below.') }}
                    </p>
                @elseif ($bandState === 'awaiting')
                    <p class="text-lg font-bold leading-tight text-base-content">
                        {{ __('Match played — result pending') }}
                    </p>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ __('The score will appear here once it has been recorded.') }}
                    </p>
                @elseif ($bandState === 'result')
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        @if ($score)
                            <p class="text-2xl font-bold leading-none text-base-content">{{ $score }}</p>
                        @endif
                        <p class="text-lg font-bold leading-tight text-base-content">{{ $resultLabel }}</p>
                    </div>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ $isHome ? __('At home against :opponent', ['opponent' => $opponent?->fullName() ?? '—'])
                                   : __('Away at :opponent', ['opponent' => $opponent?->fullName() ?? '—']) }}
                    </p>
                @endif

                {{-- Les trois réponses, tant que la compo n'est pas partie. --}}
                @if ($canAnswer)
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @foreach ($availabilityOptions as $option)
                            <x-button
                                wire:key="availability-{{ $option->value }}"
                                :label="$option->label()"
                                :icon="match ($option->value) {
                                    'available' => 'o-check-circle',
                                    'maybe' => 'o-question-mark-circle',
                                    default => 'o-x-circle',
                                }"
                                class="btn-sm {{ $myAvailability === $option
                                    ? $option->color() . ' btn'
                                    : 'btn-outline' }}"
                                wire:click="markAvailability('{{ $option->value }}')"
                                spinner="markAvailability" />
                        @endforeach

                        @if ($myAvailability)
                            <x-button :label="$myNote ? __('Edit my note') : __('Add a note')" icon="o-pencil-square"
                                class="btn-ghost btn-sm" wire:click="openNote" />
                        @endif
                    </div>

                    @if ($myNote)
                        <p class="mt-3 text-sm italic text-base-content/60">“{{ $myNote }}”</p>
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- ── L'affiche ───────────────────────────────────────────────────────── --}}
    <div class="mb-8 rounded-2xl border border-base-300 bg-base-100 p-6">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            {{-- Catégorie et division seulement : le nom de l'équipe est en gros
            juste en dessous, l'y répéter ne dit rien de plus. --}}
            <span class="text-xs font-bold uppercase tracking-wide text-base-content/50">
                {{ collect([$categoryLabel, $division])->filter()->implode(' · ') ?: __('Interclubs') }}
            </span>
            @if ($isHome)
                <x-badge class="badge-neutral badge-xs font-bold" :value="__('Home')" />
            @else
                <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" :value="__('Away')" />
            @endif
        </div>

        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
            <span class="text-xl font-bold text-base-content sm:text-2xl">{{ $team?->fullName() ?? '—' }}</span>
            <span class="text-sm font-semibold uppercase tracking-wide text-base-content/40">vs</span>
            <span class="text-xl font-bold text-base-content sm:text-2xl">{{ $opponent?->fullName() ?? '—' }}</span>
        </div>

        <div class="mt-5 space-y-2 text-sm">
            <div class="flex items-center gap-2">
                <x-icon name="o-calendar" class="h-4 w-4 shrink-0 text-base-content/40" />
                <span class="font-semibold text-base-content">
                    {{ $interclub->start_date_time->translatedFormat('l j F Y') }}
                </span>
                <span class="text-base-content/50">·</span>
                <span class="font-semibold text-base-content">
                    {{ $interclub->start_date_time->format('H\hi') }}
                </span>
            </div>

            <div class="flex items-start gap-2">
                <x-icon name="o-map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/40" />
                <div class="min-w-0">
                    <p class="text-base-content">{{ $address ?: __('Address not known') }}</p>
                    @if ($mapsUrl)
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                            class="link link-primary text-xs font-semibold">
                            {{ __('Open in Maps') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Rien à mettre dans un agenda pour une rencontre déjà jouée. --}}
        @if (! $isPast)
            <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-base-300 pt-4">
                <x-button :label="__('Add to my calendar')" icon="o-calendar-days" class="btn-primary btn-sm"
                    link="{{ $icsUrl }}" no-wire-navigate />
                <a href="{{ route('admin.user.calendar', auth()->user()) }}"
                    class="link link-hover text-xs font-semibold text-base-content/60">
                    {{ __('Receive all my matches automatically') }} →
                </a>
            </div>
        @endif
    </div>

    {{-- ── Composition et mot du capitaine ────────────────────────────────── --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card class="shadow-sm" :title="__('Line-up')" icon="o-user-group" separator>
            @if ($lineupPublished)
                <div class="divide-y divide-base-200">
                    @foreach ($lineup as $player)
                        <div class="flex items-center gap-3 py-2.5" wire:key="lineup-{{ $player->id }}">
                            <x-icon name="o-user" class="h-4 w-4 shrink-0 text-base-content/30" />
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-base-content">
                                {{ $player->full_name }}
                            </span>
                            @if ($player->id === auth()->id())
                                <x-badge class="badge-primary badge-xs font-bold" :value="__('You')" />
                            @endif
                            @if ($captain && $player->id === $captain->id)
                                <x-badge class="badge-secondary badge-xs font-bold" :value="__('Captain')" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif ($isPast && $tally->isNotEmpty())
                {{-- Rien ici : les résultats individuels juste en dessous nomment
                ceux qui ont joué. Annoncer qu'aucune composition n'a été
                enregistrée au-dessus de la liste des joueurs se contredit. --}}
            @elseif ($isPast)
                {{-- « pas encore » ne veut rien dire d'une rencontre de l'an dernier,
                et le décompte des réponses n'intéresse plus personne. --}}
                <p class="text-sm text-base-content/70">
                    {{ __('No line-up was recorded for this match.') }}
                </p>
            @else
                <p class="text-sm text-base-content/70">
                    {{ __('The captain has not published the line-up yet.') }}
                </p>

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold">
                    @if ($counts['available'] > 0)
                        <span class="text-success">{{ trans_choice(':count available|:count available', $counts['available']) }}</span>
                    @endif
                    @if ($counts['maybe'] > 0)
                        <span class="text-warning">{{ trans_choice(':count uncertain|:count uncertain', $counts['maybe']) }}</span>
                    @endif
                    @if ($counts['unavailable'] > 0)
                        <span class="text-error">{{ trans_choice(':count unavailable|:count unavailable', $counts['unavailable']) }}</span>
                    @endif
                    @if ($counts['no_response'] > 0)
                        <span class="text-base-content/40">{{ trans_choice(':count without response|:count without response', $counts['no_response']) }}</span>
                    @endif
                </div>
            @endif

            {{-- Le relevé de la fédération quand il est arrivé, sinon les noms
            que le club connaît. --}}
            @if ($isPast && $tally->isNotEmpty())
                {{-- Le filet ne se justifie que s'il sépare de quelque chose :
                sans composition au-dessus, il doublait celui de la carte. --}}
                <div @class(['mt-5 border-t border-base-300 pt-4' => $lineupPublished])>
                    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-base-content/50">
                        {{ __('Individual results') }}
                    </p>

                    <div class="divide-y divide-base-200">
                        @foreach ($tally as $row)
                            <div class="flex items-center gap-3 py-2" wire:key="tally-{{ $loop->index }}">
                                <span @class([
                                    'min-w-0 flex-1 truncate text-sm',
                                    'font-bold text-base-content' => $row['is_me'],
                                    'text-base-content' => ! $row['is_me'],
                                ])>{{ $row['label'] }}</span>

                                @if ($row['is_me'])
                                    <x-badge class="badge-primary badge-xs font-bold" :value="__('You')" />
                                @endif

                                <span class="shrink-0 text-sm font-bold tabular-nums text-base-content">
                                    {{ $row['wins'] }}<span class="font-normal text-base-content/40">/{{ $row['played'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif ($isPast && $players->isNotEmpty())
                <div class="mt-5 border-t border-base-300 pt-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-base-content/50">
                        {{ __('Played that day') }}
                    </p>
                    <p class="text-sm text-base-content">
                        {{ $players->map(fn ($player) => $player->full_name)->implode(', ') }}
                    </p>
                </div>
            @endif
        </x-card>

        <x-card class="shadow-sm" :title="__('Your captain')" icon="o-megaphone" separator>
            @if ($interclub->captain_message)
                <p class="mb-5 whitespace-pre-line rounded-xl border border-base-300 bg-base-200/50 p-4 text-sm italic text-base-content">
                    “{{ $interclub->captain_message }}”
                </p>
            @endif

            @if ($captain)
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-base-content">{{ $captain->full_name }}</p>
                        <p class="text-xs text-base-content/50">{{ __('Captain') }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($captain->phone_number)
                        <x-button :label="$captain->phone_number" icon="o-phone" class="btn-outline btn-sm"
                            link="tel:{{ $captain->phone_number }}" no-wire-navigate />
                    @endif
                    @if ($captain->email)
                        <x-button :label="__('Send an email')" icon="o-envelope" class="btn-outline btn-sm"
                            link="mailto:{{ $captain->email }}" no-wire-navigate />
                    @endif
                </div>

                @if ($lineupPublished && ! $isPast)
                    <x-admin.shared.info-alert class="mt-4" icon="o-information-circle">
                        {{ __('The line-up has been sent. If you can no longer play, contact your captain directly — changing an answer here would not warn anyone.') }}
                    </x-admin.shared.info-alert>
                @endif
            @else
                <x-empty-state icon="o-user-circle" :heading="__('No captain recorded for this team.')" />
            @endif
        </x-card>
    </div>

    {{-- ── Feuille de match ───────────────────────────────────────────────── --}}
    @if ($sheet->isNotEmpty())
        <div class="mt-6" x-data="{ open: false }">
            <button type="button"
                class="flex w-full items-center gap-3 rounded-xl border border-base-300 bg-base-100 px-4 py-3 text-left transition-colors hover:bg-base-200/50"
                @click="open = !open">
                <x-icon name="o-table-cells" class="h-4 w-4 shrink-0 opacity-40" />
                <span class="flex-1 text-sm font-bold">{{ __('Match sheet') }}</span>
                <span class="text-xs opacity-40">{{ trans_choice(':count match|:count matches', $sheet->count()) }}</span>
                <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200"
                    ::class="open ? '' : '-rotate-90'" />
            </button>

            <div x-show="open" x-collapse>
                {{-- Le seul tableau de la page : il déborde plutôt que de comprimer
                les noms sur un téléphone. --}}
                <div class="mt-3 overflow-x-auto rounded-xl border border-base-300">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>{{ __('Our player') }}</th>
                                <th>{{ __('Opponent') }}</th>
                                <th class="text-right">{{ __('Sets') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sheet as $line)
                                <tr wire:key="sheet-{{ $line->id }}" @class(['bg-primary/5' => $line->user_id === auth()->id()])>
                                    <td class="tabular-nums opacity-50">{{ $line->position }}</td>
                                    <td>
                                        @if ($line->is_double)
                                            <span class="italic opacity-60">{{ __('Doubles') }}</span>
                                        @else
                                            <span @class(['font-bold' => $line->user_id === auth()->id()])>
                                                {{ $line->user?->full_name ?? $line->our_player_name ?? '—' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($line->is_double)
                                            <span class="opacity-40">—</span>
                                        @else
                                            {{ $line->opponent_name ?? '—' }}
                                            @if ($line->opponent_ranking)
                                                <span class="opacity-50">({{ $line->opponent_ranking }})</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right tabular-nums">
                                        @if ($line->is_forfeit)
                                            <span class="text-xs italic opacity-60">{{ __('Forfeit') }}</span>
                                        @else
                                            {{ $line->setScore() ?? '—' }}
                                        @endif
                                        <x-icon :name="$line->we_won ? 'o-check' : 'o-x-mark'" @class([
                                            'ml-1 inline h-4 w-4 align-text-bottom',
                                            'text-success' => $line->we_won,
                                            'text-error' => ! $line->we_won,
                                        ]) />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Note de disponibilité ──────────────────────────────────────────── --}}
    <x-app-modal wire:model="noteModal" :title="__('Add a note for your captain')" :open="$noteModal" separator>
        <x-textarea wire:model.live.blur="availabilityNote" :label="__('Note')"
            :placeholder="__('e.g. I can only arrive at 20h')" rows="3" />

        <x-slot:actions>
            <x-button :label="__('Cancel')" class="btn-ghost" wire:click="$set('noteModal', false)" />
            @if ($myAvailability)
                <x-button :label="__('Save')" class="btn-primary" icon="o-check"
                    wire:click="markAvailability('{{ $myAvailability->value }}')" spinner="markAvailability" />
            @endif
        </x-slot:actions>
    </x-app-modal>
</div>
