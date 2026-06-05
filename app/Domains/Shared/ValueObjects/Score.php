<?php

declare(strict_types=1);

namespace App\Domains\Shared\ValueObjects;

use InvalidArgumentException;

class Score
{
    private function __construct(
        private readonly int $player1,
        private readonly int $player2,
    ) {}

    public static function make(int $player1, int $player2): self
    {
        if ($player1 < 0 || $player2 < 0) {
            throw new InvalidArgumentException('Score cannot be negative');
        }

        return new self($player1, $player2);
    }

    public function player1(): int
    {
        return $this->player1;
    }

    public function player2(): int
    {
        return $this->player2;
    }

    public function winner(): ?int
    {
        if ($this->player1 > $this->player2) {
            return 1;
        }

        if ($this->player2 > $this->player1) {
            return 2;
        }

        return null;
    }

    public function isWon(): bool
    {
        return $this->winner() !== null;
    }

    public function maxScore(): int
    {
        return max($this->player1, $this->player2);
    }

    public function minScore(): int
    {
        return min($this->player1, $this->player2);
    }

    public function difference(): int
    {
        return abs($this->player1 - $this->player2);
    }

    public function equals(Score $other): bool
    {
        return $this->player1 === $other->player1 && $this->player2 === $other->player2;
    }

    public function __toString(): string
    {
        return "{$this->player1}-{$this->player2}";
    }
}
