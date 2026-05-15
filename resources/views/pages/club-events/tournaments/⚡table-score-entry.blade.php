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

    public bool $submitted = false;

    public function mount(): void
    {
        $maxSets = ($this->tournament->sets_to_win * 2) - 1;
        $this->setScores = array_fill(0, $maxSets, ['p1' => '', 'p2' => '']);
    }

    public function submitScore(): void
    {
        $setResults = [];
        $p1Sets = 0;
        $p2Sets = 0;

        foreach ($this->setScores as $set) {
            $p1 = (int) ($set['p1'] ?? 0);
            $p2 = (int) ($set['p2'] ?? 0);

            if ($p1 === 0 && $p2 === 0) {
                continue;
            }

            $setResults[] = ['player1_score' => $p1, 'player2_score' => $p2];
            $p1 > $p2 ? $p1Sets++ : $p2Sets++;

            if ($p1Sets >= $this->tournament->sets_to_win || $p2Sets >= $this->tournament->sets_to_win) {
                break;
            }
        }

        if (empty($setResults)) {
            $this->error(__('Please enter at least one set score.'));

            return;
        }

        if ($p1Sets < $this->tournament->sets_to_win && $p2Sets < $this->tournament->sets_to_win) {
            $this->error(__('Match not finished — a player must win :n sets.', ['n' => $this->tournament->sets_to_win]));

            return;
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

<div class="w-full max-w-sm mx-auto space-y-4">

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
        {{-- Match header --}}
        <div class="bg-base-100 rounded-2xl shadow p-5">
            <div class="text-[10px] font-bold uppercase tracking-widest opacity-40 mb-3 text-center">
                {{ $match->pool?->name ?? __('Bracket') }} · {{ __('Table') }} {{ $table->name }}
            </div>

            <div class="flex justify-between items-center gap-4">
                @php
                    $p1Sets = collect($setScores)->filter(fn ($s) => (int)($s['p1'] ?? 0) > (int)($s['p2'] ?? 0))->count();
                    $p2Sets = collect($setScores)->filter(fn ($s) => (int)($s['p2'] ?? 0) > (int)($s['p1'] ?? 0))->count();
                @endphp
                <div class="flex-1 text-center">
                    <div class="font-black text-sm uppercase leading-tight wrap-break-word hyphens-auto">{{ $match->player1?->full_name ?? '—' }}</div>
                    <div class="text-4xl font-extrabold text-primary mt-1">{{ $p1Sets }}</div>
                </div>
                <div class="text-lg font-black opacity-20 italic shrink-0">VS</div>
                <div class="flex-1 text-center">
                    <div class="font-black text-sm uppercase leading-tight wrap-break-word hyphens-auto">{{ $match->player2?->full_name ?? '—' }}</div>
                    <div class="text-4xl font-extrabold mt-1">{{ $p2Sets }}</div>
                </div>
            </div>
        </div>

        {{-- Set inputs --}}
        @php $maxSets = ($tournament->sets_to_win * 2) - 1; @endphp
        <div class="space-y-3">
            @for ($i = 0; $i < $maxSets; $i++)
                @php
                    $p1 = (int)($setScores[$i]['p1'] ?? 0);
                    $p2 = (int)($setScores[$i]['p2'] ?? 0);
                    $setDone = $p1 > 0 || $p2 > 0;
                    $p1Won = 0; $p2Won = 0;
                    for ($j = 0; $j < $i; $j++) {
                        $sp1 = (int)($setScores[$j]['p1'] ?? 0);
                        $sp2 = (int)($setScores[$j]['p2'] ?? 0);
                        if ($sp1 > $sp2) $p1Won++; else if ($sp2 > $sp1) $p2Won++;
                    }
                    $locked = $p1Won >= $tournament->sets_to_win || $p2Won >= $tournament->sets_to_win;
                @endphp

                <div @class([
                    'bg-base-100 rounded-xl shadow p-4 flex items-center gap-4 transition-all',
                    'opacity-30' => $locked,
                    'ring-2 ring-success/40' => $setDone && !$locked,
                ])>
                    <div @class([
                        'w-10 h-10 rounded-lg flex flex-col items-center justify-center shrink-0',
                        'bg-success text-success-content' => $setDone && !$locked,
                        'bg-base-200 text-base-content/40' => !$setDone || $locked,
                    ])>
                        <span class="text-[8px] uppercase font-bold">Set</span>
                        <span class="text-base font-black leading-none">{{ $i + 1 }}</span>
                    </div>

                    <x-input wire:model.live="setScores.{{ $i }}.p1"
                        type="number" min="0" max="30" placeholder="0"
                        class="input-lg text-center font-mono font-black text-2xl flex-1 p-1"
                        :disabled="$locked" />

                    <span class="text-lg font-black opacity-20">:</span>

                    <x-input wire:model.live="setScores.{{ $i }}.p2"
                        type="number" min="0" max="30" placeholder="0"
                        class="input-lg text-center font-mono font-black text-2xl flex-1 p-1"
                        :disabled="$locked" />
                </div>
            @endfor
        </div>

        <div class="pt-2">
            <x-button label="{{ __('Submit score') }}" icon="o-check"
                class="btn-primary w-full btn-lg"
                wire:click="submitScore" spinner="submitScore" />
        </div>
    @endif

</div>
