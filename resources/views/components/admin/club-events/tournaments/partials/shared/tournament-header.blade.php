{{--
    Le haut de la fiche d'un tournoi, le même sur la préparation et sur la régie.

    Il répond toujours aux deux mêmes questions : où en est le tournoi (la
    pastille, une ligne de faits) et que faire maintenant (une action au plus).
    Le reste attend derrière « ⋯ ». Sous le bandeau, les quatre phases : celle
    du statut porte un point, celle affichée est soulignée, et toutes s'ouvrent.

    Paramètres : $tournament, $shownPhase (TournamentPhaseEnum), $banner
    (facts, alert, action, menu — voir banner() dans les deux composants).
--}}
@php
    use App\Domains\Shared\Enums\TournamentPhaseEnum;
    use App\Domains\Shared\Enums\TournamentStatusEnum;

    $activePhase = TournamentPhaseEnum::activeFor($tournament);
    $cancelled = $tournament->status === TournamentStatusEnum::CANCELLED;
@endphp

<div class="mb-6 flex flex-col gap-3" data-tournament-header>
    <div @class([
        'flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4',
        'border-base-300 bg-base-100' => ! $cancelled,
        'border-error/20 bg-error/10' => $cancelled,
    ])>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold">{{ $tournament->name }}</h1>
                <x-badge :value="$tournament->status->getLabel()" :class="$tournament->status->badgeClass()" data-banner-status />
            </div>
            @if ($banner['facts'] !== '')
                <p class="mt-1 text-sm text-base-content/70" data-banner-facts>{{ $banner['facts'] }}</p>
            @endif
            @if ($banner['alert'])
                <p class="mt-0.5 text-sm font-semibold text-warning-content" data-banner-alert>{{ $banner['alert'] }}</p>
            @endif
        </div>

        @if ($banner['action'] || $banner['menu'] !== [])
            <div class="flex items-center gap-2">
                @if ($banner['action'])
                    <x-button :label="$banner['action']['label']" :icon="$banner['action']['icon']"
                        class="btn-primary btn-sm" :wire:click="$banner['action']['wire']" data-banner-action />
                @endif

                @if ($banner['menu'] !== [])
                    <x-dropdown icon="o-ellipsis-horizontal" right class="btn-ghost btn-sm" :aria-label="__('More actions')" data-banner-menu>
                        @foreach ($banner['menu'] as $item)
                            @if (isset($item['href']))
                                <x-menu-item :title="$item['label']" :icon="$item['icon']" :link="$item['href']" external />
                            @else
                                <x-menu-item :title="$item['label']" :icon="$item['icon']" :wire:click="$item['wire']"
                                    :class="($item['danger'] ?? false) ? 'text-error' : ''" />
                            @endif
                        @endforeach
                    </x-dropdown>
                @endif
            </div>
        @endif
    </div>

    <nav class="flex flex-wrap gap-1" aria-label="{{ __('Phases') }}" data-phases>
        @foreach (TournamentPhaseEnum::inOrder() as $phase)
            @php
                $isShown = $phase === $shownPhase;
                $isActive = $phase === $activePhase;
                $isDone = $activePhase !== null && $phase->position() < $activePhase->position();
            @endphp
            <a href="{{ $phase->urlFor($tournament) }}" wire:navigate data-phase="{{ $phase->value }}"
                @if ($isShown) aria-current="page" @endif
                @class([
                    'flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-semibold transition-colors',
                    'border-base-300 bg-base-100 text-primary' => $isShown,
                    'border-transparent hover:bg-base-100' => ! $isShown,
                    'text-base-content' => ! $isShown && $phase->isOpenFor($tournament),
                    'text-base-content/70' => ! $isShown && ! $phase->isOpenFor($tournament),
                ])>
                @if ($isDone)
                    <x-icon name="o-check" class="h-3.5 w-3.5 text-success" />
                @endif
                {{ $phase->label() }}
                @if ($isActive)
                    <span class="h-2 w-2 rounded-full bg-warning" aria-label="{{ __('Current phase') }}"></span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
