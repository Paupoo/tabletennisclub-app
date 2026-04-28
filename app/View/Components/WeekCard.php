<?php


declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;


class WeekCard extends Component
{
    public string $status;

    public function __construct(
        public int $week,
        public string $opponent,
        public string $date,
        public ?array $score = null,
        public ?array $matches = null,
        public int $selectionCount = 0,
        ?string $status = null,   // passé explicitement depuis le planning
    ) {
        // Si un status est fourni explicitement, on l'utilise.
        // Sinon, on le calcule depuis le score.
        $this->status = $status ?? $this->resolveStatus();
    }

    private function resolveStatus(): string
    {
        if ($this->score === null) {
            return 'future';
        }

        if ($this->score['home'] > $this->score['away']) {
            return 'win';
        }
        if ($this->score['home'] < $this->score['away']) {
            return 'loss';
        }

        return 'draw';
    }

    public function isExpandable(): bool
    {
        return in_array($this->status, ['win', 'loss', 'draw'])
            && ! empty($this->matches);
    }

    public function barColor(): string
    {
        return match ($this->status) {
            'win' => 'bg-success',
            'loss' => 'bg-error',
            'draw' => 'bg-base-400',
            'pending' => 'bg-warning',
            'ready' => 'bg-success',
            default => 'bg-base-300',
        };
    }

    public function barOpacity(): string
    {
        return match ($this->status) {
            'win', 'loss', 'pending' => 'opacity-70',
            'ready' => 'opacity-30',
            default => 'opacity-40',
        };
    }

    public function dotStyle(): string
    {
        return match ($this->status) {
            'win' => 'bg-success',
            'loss' => 'bg-error',
            'draw' => 'bg-base-400',
            'pending' => 'border-2 border-warning',
            'ready' => 'bg-success opacity-40',
            default => 'border border-base-300',
        };
    }

    public function scoreHomeClass(): string
    {
        return match ($this->status) {
            'win' => 'bg-success/15 text-success',
            'loss' => 'bg-error/15 text-error',
            default => 'bg-base-200 text-base-content',
        };
    }

    public function render()
{
    return view('components.week-card', [
        'isExpandable'   => $this->isExpandable(),
        'barColor'       => $this->barColor(),
        'barOpacity'     => $this->barOpacity(),
        'dotStyle'       => $this->dotStyle(),
        'scoreHomeClass' => $this->scoreHomeClass(),
        'status'         => $this->status,
    ]);
}
}