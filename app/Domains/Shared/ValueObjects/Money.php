<?php

declare(strict_types=1);

namespace App\Domains\Shared\ValueObjects;

use InvalidArgumentException;

class Money implements \Stringable
{
    private function __construct(private readonly int $cents) {}

    public function __toString(): string
    {
        return number_format($this->euros(), 2, ',', ' ') . '€';
    }

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        return new self($cents);
    }

    public static function fromEuros(float $euros): self
    {
        return self::fromCents((int) round($euros * 100));
    }

    public function add(Money $other): Money
    {
        return new self($this->cents + $other->cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function euros(): float
    {
        return round($this->cents / 100, 2);
    }

    public function isGreaterThan(Money $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function isLessThan(Money $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function multiply(float $factor): Money
    {
        return self::fromCents((int) round($this->cents * $factor));
    }

    public function subtract(Money $other): Money
    {
        return self::fromCents($this->cents - $other->cents);
    }
}
