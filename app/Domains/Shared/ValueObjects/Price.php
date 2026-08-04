<?php

declare(strict_types=1);

namespace App\Domains\Shared\ValueObjects;

use InvalidArgumentException;

class Price implements \Stringable
{
    private function __construct(private readonly int $cents) {}

    public function __toString(): string
    {
        return number_format($this->euros(), 2, ',', ' ') . '€';
    }

    public static function free(): self
    {
        return new self(0);
    }

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Price cannot be negative');
        }

        return new self($cents);
    }

    public static function fromEuros(float $euros): self
    {
        if ($euros < 0) {
            throw new InvalidArgumentException('Price cannot be negative');
        }

        return self::fromCents((int) round($euros * 100));
    }

    public function add(Price $other): Price
    {
        return new self($this->cents + $other->cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function equals(Price $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function euros(): float
    {
        return round($this->cents / 100, 2);
    }

    public function isFree(): bool
    {
        return $this->cents === 0;
    }

    public function isGreaterThan(Price $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function isLessThan(Price $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function multiply(int $quantity): Price
    {
        return new self($this->cents * $quantity);
    }

    public function subtract(Price $other): Price
    {
        return self::fromCents($this->cents - $other->cents);
    }
}
