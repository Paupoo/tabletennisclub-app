<?php

declare (strict_types=1);

namespace App\Support;

class Captcha
{
    public static function generate(): array
    {
        $a = rand(0, 10);
        $b = rand(0, 10);
        $operations = ['+', '*'];
        $operation = $operations[array_rand($operations)];

        return [
            'a' => $a,
            'b' => $b,
            'operation' => $operation
        ];
    }


    public function validate(array $captcha, int $userResult): bool
    {
        $a = $captcha['a'];
        $b = $captcha['b'];
        $operation = $captcha['operation'];

        $correctResult = match ($operation) {
            '+' => $a + $b,
            '*' => $a * $b,
            default => null,
        };

        return $userResult === $correctResult;
    }
}