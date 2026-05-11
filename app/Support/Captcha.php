<?php

declare(strict_types=1);

namespace App\Support;

class Captcha
{
    public static function generate(): array
    {
        $a = random_int(0, 10);
        $b = random_int(0, 10);
        $operations = ['+', '*'];
        $operation = $operations[random_int(0, count($operations) - 1)];

        return [
            'a' => $a,
            'b' => $b,
            'operation' => $operation,
        ];
    }

    public function validate(array $captcha, int $userResult): bool
    {
        if (!isset($captcha['a'], $captcha['b'], $captcha['operation'])) {
            return false;
        }
        
        if(!is_int($captcha['a']) || !is_int($captcha['b'])) {
            throw new \TypeError('Captcha values must be integers');
        }

        if($captcha['a'] < 0 || $captcha['a'] > 10 || $captcha['b'] < 0 || $captcha['b'] > 10) {
            throw new \InvalidArgumentException('Captcha values must be between 0 and 10');
        }

        if(!in_array($captcha['operation'], ['+', '*'])) {
            throw new \InvalidArgumentException('Invalid captcha operation');
        }


        $a = $captcha['a'];
        $b = $captcha['b'];
        $operation = $captcha['operation'];

        $correctResult = match ($operation) {
            '+' => $a + $b,
            '*' => $a * $b,
        };

        return $userResult === $correctResult;
    }
}
