{{-- resources/views/components/admin/club-events/tournaments/tournament-card.blade.php --}}
@props(['tournament'])

@php
    $statusMap = [
        'published' => [
            'bg'    => 'bg-success/10',    // Fond très léger de la couleur "success" du thème
            'text'  => 'text-success',     // Texte couleur "success" (auto-adapté au dark)
            'badge' => 'badge-success',    // Composant daisyUI natif
        ],
        'pending' => [
            'bg'    => 'bg-info/10',
            'text'  => 'text-info',
            'badge' => 'badge-info',
        ],
        'locked' => [
            'bg'    => 'bg-error/10',
            'text'  => 'text-error',
            'badge' => 'badge-error',
        ],
        'closed' => [
            'bg'    => 'bg-base-200',      // Gris neutre du thème
            'text'  => 'text-base-content/70',
            'badge' => 'badge-ghost',
        ],
        'cancelled' => [
            'bg'    => 'bg-warning/10',
            'text'  => 'text-warning',
            'badge' => 'badge-warning',
        ],
        'draft' => [
            'bg'    => 'bg-base-300',
            'text'  => 'text-base-content/60',
            'badge' => 'badge-outline',
        ],
        'setup' => [
            'bg'    => 'bg-primary/10',
            'text'  => 'text-primary',
            'badge' => 'badge-primary',
        ],
    ];

    $status = $statusMap[$tournament->status->value] ?? $statusMap['closed'];
    $percent = ($tournament->total_users / max(1, $tournament->max_users)) * 100;
@endphp

{{-- On utilise base-100 (fond) et base-200 (bordure) --}}
<div class="group overflow-hidden rounded-xl border border-base-200 bg-base-100 shadow-sm transition-all hover:shadow-md">

    {{-- HEADER --}}
    {{-- On garde $status['bg'] mais on s'assure qu'il utilise des couleurs de thème ou une opacité --}}
    <div class="{{ $status['bg'] }} px-4 py-3 bg-opacity-10 dark:bg-opacity-20">
        <div class="flex items-center justify-between">
            <div>
                <p class="{{ $status['text'] }} text-[10px] uppercase tracking-wider font-bold">
                    {{ __('Tournament') }}
                </p>

                {{-- base-content s'adapte auto (noir en light, blanc cassé en dark) --}}
                <p class="text-[15px] font-semibold text-base-content">
                    {{ $tournament['name'] }}
                </p>
            </div>

            <span class="{{ $status['badge'] }} badge badge-sm uppercase font-bold py-3">
                {{ $tournament->status->value }}
            </span>
        </div>
    </div>

    {{-- BODY --}}
    <div class="px-4 py-3">

        {{-- Infos : text-base-content/70 pour un effet désactivé propre --}}
        <div class="mb-3 space-y-1.5 text-[13px] text-base-content/70">

            <div class="flex items-center gap-2">
                <x-icon class="h-4 w-4 opacity-50" name="o-calendar" />
                {{ $tournament->start_date->format('d M Y H:i') }}
            </div>

            <div class="flex items-center gap-2">
                <x-icon class="h-4 w-4 opacity-50" name="o-users" />
                {{ $tournament->total_users }} / {{ $tournament->max_users }}
            </div>

            <div class="flex items-center gap-2">
                <x-icon class="h-4 w-4 opacity-50" name="o-banknotes" />
                {{ $tournament->price }} €
            </div>

        </div>

        {{-- Progress --}}
        <div class="mb-3">
            <progress 
                class="progress {{ $percent >= 100 ? 'progress-error' : 'progress-primary' }} h-1.5" 
                max="100"
                value="{{ $percent }}">
            </progress>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end border-t border-base-200 pt-2">

            <div class="flex items-center gap-1">

                <a class="btn btn-ghost btn-sm btn-square text-base-content/60 hover:text-primary"
                    href="{{ route('admin.tournaments.wizard') }}" title="{{ __('Settings') }}">
                    <x-heroicon-o-cog-6-tooth class="h-4 w-4" />
                </a>

                @if ($tournament->status !== 'closed')
                    <a class="btn btn-ghost btn-sm btn-square text-base-content/60 hover:text-info"
                        href="{{ route('admin.tournaments.live-center', 1) }}" title="{{ __('Live') }}">
                        <x-heroicon-o-rocket-launch class="h-4 w-4" />
                    </a>
                @endif

                <button class="btn btn-ghost btn-sm btn-square text-error/60 hover:bg-error/10 hover:text-error"
                    title="{{ __('Delete') }}">
                    <x-heroicon-o-trash class="h-4 w-4" />
                </button>

            </div>
        </div>
    </div>
</div>
