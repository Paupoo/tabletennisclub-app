<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * A value axis with round ticks: 0 / 500 / 1 000 / 1 500 rather than
 * 0 / 487 / 974.
 *
 * Always includes zero, so a bar grows from a true baseline and a result
 * that dips below zero keeps its sign in sight.
 */
final readonly class ChartScale
{
    /**
     * @param  list<float>  $ticks
     */
    private function __construct(
        public float $min,
        public float $max,
        public array $ticks,
    ) {}

    /**
     * @param  iterable<float|int>  $values
     */
    public static function covering(iterable $values, int $targetTicks = 4): self
    {
        $low = 0.0;
        $high = 0.0;

        foreach ($values as $value) {
            $low = min($low, (float) $value);
            $high = max($high, (float) $value);
        }

        if ($low === $high) {
            $high = $low + 1;
        }

        $step = self::niceStep(($high - $low) / max(1, $targetTicks));
        $min = floor($low / $step) * $step;
        $max = ceil($high / $step) * $step;

        $ticks = [];

        for ($tick = $min; $tick <= $max + $step / 2; $tick += $step) {
            $ticks[] = round($tick, 2);
        }

        return new self($min, $max, $ticks);
    }

    /**
     * Where a value falls between two pixel positions: `$from` for the
     * scale's minimum, `$to` for its maximum.
     */
    public function position(float $value, float $from, float $to): float
    {
        return round($from + ($value - $this->min) / ($this->max - $this->min) * ($to - $from), 2);
    }

    private static function niceStep(float $rough): float
    {
        $magnitude = 10 ** floor(log10($rough));
        $fraction = $rough / $magnitude;

        $nice = match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 2.5 => 2.5,
            $fraction <= 5 => 5,
            default => 10,
        };

        return $nice * $magnitude;
    }
}
