<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentFinalPhaseService;
use App\Services\TournamentTableService;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public Tournament $tournament;

    public Table $table;

    public TournamentMatch $match;

    /** @var array<int, array{p1: string, p2: string}> */
    public array $setScores = [];

    public int $p1Handicap = 0;

    public int $p2Handicap = 0;

    public bool $submitted = false;

    public function mount(): void
    {
        $maxSets = ($this->tournament->sets_to_win * 2) - 1;

        $this->p1Handicap = $this->tournament->has_handicap_points ? $this->match->player1_handicap_points : 0;
        $this->p2Handicap = $this->tournament->has_handicap_points ? $this->match->player2_handicap_points : 0;

        $this->setScores = array_fill(0, $maxSets, ['p1' => (string) $this->p1Handicap, 'p2' => (string) $this->p2Handicap]);

        // Pre-load any previously saved sets
        $this->match->loadMissing('sets');
        foreach ($this->match->sets as $set) {
            $idx = $set->set_number - 1;
            if (isset($this->setScores[$idx])) {
                $this->setScores[$idx] = ['p1' => (string) $set->player1_score, 'p2' => (string) $set->player2_score];
            }
        }
    }

    /**
     * Parse current setScores into valid set results, stopping once a winner is found.
     * Empty detection uses handicap starting values (Approach B).
     *
     * @return array{results: array<int, array{player1_score: int, player2_score: int}>, p1Sets: int, p2Sets: int}
     */
    private function parseSetResults(): array
    {
        $results = [];
        $p1Sets = 0;
        $p2Sets = 0;

        foreach ($this->setScores as $set) {
            $p1 = (int) ($set['p1'] ?? 0);
            $p2 = (int) ($set['p2'] ?? 0);

            if ($p1 === $this->p1Handicap && $p2 === $this->p2Handicap) {
                continue;
            }

            $results[] = ['player1_score' => $p1, 'player2_score' => $p2];
            $p1 > $p2 ? $p1Sets++ : $p2Sets++;

            if ($p1Sets >= $this->tournament->sets_to_win || $p2Sets >= $this->tournament->sets_to_win) {
                break;
            }
        }

        return compact('results', 'p1Sets', 'p2Sets');
    }

    public function saveDraft(): void
    {
        ['results' => $setResults] = $this->parseSetResults();

        if (empty($setResults)) {
            $this->error(__('No set scores to save.'));

            return;
        }

        $this->match->saveDraft($setResults);
        $this->success(__('Sets saved.'));
    }

    public function submitScore(): void
    {
        ['results' => $setResults, 'p1Sets' => $p1Sets, 'p2Sets' => $p2Sets] = $this->parseSetResults();

        if (empty($setResults)) {
            $this->error(__('Please enter at least one set score.'));

            return;
        }

        if ($p1Sets < $this->tournament->sets_to_win && $p2Sets < $this->tournament->sets_to_win) {
            $this->error(__('Match not finished — a player must win :n sets.', ['n' => $this->tournament->sets_to_win]));

            return;
        }

        foreach ($setResults as $i => $set) {
            $error = $this->tournament->validateSetScore($set['player1_score'], $set['player2_score'], $i + 1, $this->p1Handicap, $this->p2Handicap);
            if ($error) {
                $this->error($error);

                return;
            }
        }

        $this->match->recordResult($setResults);

        app(TournamentTableService::class)->freeUsedTable($this->match);

        if ($this->match->round !== null && $this->match->winner_id) {
            app(TournamentFinalPhaseService::class)->completeMatch($this->match, $this->match->winner_id);
        }

        $this->submitted = true;

        $winner = $this->match->winner_id === $this->match->player1_id
            ? $this->match->player1
            : $this->match->player2;

        $this->success($winner->full_name . ' ' . __('wins!'));
    }

    public function render(): View
    {
        return view('pages.club-events.tournaments.⚡table-score-entry');
    }
};
?>

<div class="w-full max-w-sm mx-auto space-y-4"
    x-data="{ confirmOpen: false }">

    @if ($submitted)
        <div class="bg-base-100 rounded-2xl shadow p-8 text-center space-y-4">
            <x-icon name="o-check-circle" class="w-14 h-14 mx-auto text-success" />
            <h2 class="text-lg font-bold text-success">{{ __('Score submitted!') }}</h2>
            <p class="text-sm text-base-content/60">{{ __('The result has been recorded. Thank you!') }}</p>
            <a href="{{ route('admin.tournaments.live-center', $tournament) }}" class="btn btn-ghost btn-sm">
                {{ __('Back to Live Center') }}
            </a>
        </div>
    @else
        @php
            $maxSets  = ($tournament->sets_to_win * 2) - 1;
            $hp1      = $p1Handicap ?? 0;
            $hp2      = $p2Handicap ?? 0;
            $doneSets = collect($setScores)->filter(fn ($s) => !((int)($s['p1'] ?? 0) === $hp1 && (int)($s['p2'] ?? 0) === $hp2));
            $p1Sets   = $doneSets->filter(fn ($s) => (int)($s['p1'] ?? 0) > (int)($s['p2'] ?? 0))->count();
            $p2Sets   = $doneSets->filter(fn ($s) => (int)($s['p2'] ?? 0) > (int)($s['p1'] ?? 0))->count();
            $matchFinished = $p1Sets >= $tournament->sets_to_win || $p2Sets >= $tournament->sets_to_win;
            $hasSets  = $doneSets->isNotEmpty();
            $winner   = $matchFinished
                ? ($p1Sets >= $tournament->sets_to_win ? $match->player1 : $match->player2)
                : null;
        @endphp

        {{-- Match header --}}
        <div class="bg-base-100 rounded-2xl shadow p-5">
            <div class="text-[10px] font-bold uppercase tracking-widest opacity-40 mb-3 text-center">
                {{ $match->pool?->name ?? __('Bracket') }} · {{ __('Table') }} {{ $table->name }}
            </div>

            <div class="flex justify-between items-center gap-4">
                <div class="flex-1 text-center">
                    <div class="font-black text-sm uppercase leading-tight wrap-break-word hyphens-auto
                        {{ $matchFinished && $p1Sets >= $tournament->sets_to_win ? 'text-success' : '' }}">
                        {{ $match->player1?->full_name ?? '—' }}
                    </div>
                    <div class="text-4xl font-extrabold mt-1
                        {{ $matchFinished && $p1Sets >= $tournament->sets_to_win ? 'text-success' : 'text-primary' }}">
                        {{ $p1Sets }}
                    </div>
                </div>
                <div class="text-lg font-black opacity-20 italic shrink-0">VS</div>
                <div class="flex-1 text-center">
                    <div class="font-black text-sm uppercase leading-tight wrap-break-word hyphens-auto
                        {{ $matchFinished && $p2Sets >= $tournament->sets_to_win ? 'text-success' : '' }}">
                        {{ $match->player2?->full_name ?? '—' }}
                    </div>
                    <div class="text-4xl font-extrabold mt-1
                        {{ $matchFinished && $p2Sets >= $tournament->sets_to_win ? 'text-success' : '' }}">
                        {{ $p2Sets }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Handicap info bar --}}
        @if ($tournament->has_handicap_points && ($hp1 > 0 || $hp2 > 0))
            <div class="bg-base-100 rounded-2xl shadow px-5 py-4">
                <p class="text-[10px] font-bold uppercase tracking-widest text-warning text-center mb-3">
                    ⚡ {{ __('Handicap per set — starting scores') }}
                </p>
                <div class="flex justify-between items-center gap-4">
                    <div class="flex-1 text-center">
                        <div class="text-[10px] font-bold opacity-60 truncate">{{ $match->player1?->full_name ?? '—' }}</div>
                        <div @class(['text-3xl font-extrabold leading-none mt-1', 'text-warning' => $hp1 > 0, 'text-base-content/30' => $hp1 === 0])>
                            +{{ $hp1 }}
                        </div>
                    </div>
                    <div class="text-xs font-bold opacity-30">pts</div>
                    <div class="flex-1 text-center">
                        <div class="text-[10px] font-bold opacity-60 truncate">{{ $match->player2?->full_name ?? '—' }}</div>
                        <div @class(['text-3xl font-extrabold leading-none mt-1', 'text-warning' => $hp2 > 0, 'text-base-content/30' => $hp2 === 0])>
                            +{{ $hp2 }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Set inputs (all always editable) --}}
        <div class="space-y-3">
            @for ($i = 0; $i < $maxSets; $i++)
                @php
                    $p1      = (int)($setScores[$i]['p1'] ?? $hp1);
                    $p2      = (int)($setScores[$i]['p2'] ?? $hp2);
                    $isEmpty = ($p1 === $hp1 && $p2 === $hp2);
                    $sMax    = max($p1, $p2);
                    $sMin    = min($p1, $p2);
                    $setWon  = ! $isEmpty && $p1 !== $p2 && (
                        $tournament->deuce_enabled
                            ? (($sMin < 10 && $sMax === 11) || ($sMin >= 10 && $sMax - $sMin === 2))
                            : ($sMax === 11)
                    );
                @endphp

                <div @class([
                    'bg-base-100 rounded-xl shadow p-4 flex items-center gap-4 transition-all',
                    'ring-2 ring-success/40' => $setWon,
                ])>
                    <div @class([
                        'w-10 h-10 rounded-lg flex flex-col items-center justify-center shrink-0',
                        'bg-success text-success-content' => $setWon,
                        'bg-base-200 text-base-content/40' => ! $setWon,
                    ])>
                        <span class="text-[8px] uppercase font-bold">Set</span>
                        <span class="text-base font-black leading-none">{{ $i + 1 }}</span>
                    </div>

                    <x-input wire:model.live="setScores.{{ $i }}.p1"
                        type="number" min="{{ $hp1 }}" max="30" placeholder="{{ $hp1 }}"
                        class="input-lg text-center font-mono font-black text-2xl flex-1 p-1" />

                    <span class="text-lg font-black opacity-20">:</span>

                    <x-input wire:model.live="setScores.{{ $i }}.p2"
                        type="number" min="{{ $hp2 }}" max="30" placeholder="{{ $hp2 }}"
                        class="input-lg text-center font-mono font-black text-2xl flex-1 p-1" />
                </div>
            @endfor
        </div>

        {{-- Actions --}}
        <div class="pt-2 flex flex-col gap-2">
            @if ($matchFinished)
                <button @click="confirmOpen = true"
                    class="btn btn-success w-full btn-lg">
                    <x-icon name="o-trophy" class="w-5 h-5" />
                    {{ __('Submit score') }}
                </button>
            @endif

            @if ($hasSets && ! $matchFinished)
                <x-button label="{{ __('Save sets') }}" icon="o-arrow-down-tray"
                    class="btn-outline w-full btn-lg"
                    wire:click="saveDraft" spinner="saveDraft" />
            @endif
        </div>

        {{-- Confirm modal --}}
        <div x-show="confirmOpen" x-cloak
            @click.self="confirmOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">
            <div class="bg-base-100 rounded-2xl shadow-2xl p-6 w-full max-w-xs space-y-4 text-center">
                <x-icon name="o-trophy" class="w-12 h-12 mx-auto text-success" />

                @if ($winner)
                    <div>
                        <p class="text-xs uppercase font-bold opacity-40 mb-1">{{ __('Winner') }}</p>
                        <p class="text-xl font-black">{{ $winner->full_name }}</p>
                        <p class="text-4xl font-extrabold text-success mt-1">{{ $p1Sets }} — {{ $p2Sets }}</p>
                    </div>
                @endif

                <p class="text-sm opacity-60">{{ __('Confirm and record this result?') }}</p>

                <div class="flex gap-2 pt-1">
                    <button @click="confirmOpen = false" class="btn btn-ghost flex-1">
                        {{ __('Cancel') }}
                    </button>
                    <button
                        wire:click="submitScore"
                        wire:loading.attr="disabled"
                        @click="confirmOpen = false"
                        class="btn btn-success flex-1">
                        <wire:loading wire:target="submitScore">
                            <span class="loading loading-spinner loading-sm"></span>
                        </wire:loading>
                        {{ __('Confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
