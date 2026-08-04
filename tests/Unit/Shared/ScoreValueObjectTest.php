<?php

declare(strict_types=1);

use App\Domains\Shared\ValueObjects\Score;

test('score can be created', function (): void {
    $score = Score::make(11, 9);
    expect($score->player1())->toBe(11);
    expect($score->player2())->toBe(9);
});

test('score detects player 1 winner', function (): void {
    $score = Score::make(11, 9);
    expect($score->winner())->toBe(1);
    expect($score->isWon())->toBeTrue();
});

test('score detects player 2 winner', function (): void {
    $score = Score::make(9, 11);
    expect($score->winner())->toBe(2);
    expect($score->isWon())->toBeTrue();
});

test('score recognizes tied score as not won', function (): void {
    $score = Score::make(10, 10);
    expect($score->winner())->toBeNull();
    expect($score->isWon())->toBeFalse();
});

test('score calculates max score', function (): void {
    $score = Score::make(11, 9);
    expect($score->maxScore())->toBe(11);
});

test('score calculates min score', function (): void {
    $score = Score::make(11, 9);
    expect($score->minScore())->toBe(9);
});

test('score calculates difference', function (): void {
    $score = Score::make(13, 11);
    expect($score->difference())->toBe(2);
});

test('score difference works with reversed scores', function (): void {
    $score = Score::make(9, 11);
    expect($score->difference())->toBe(2);
});

test('score rejects negative player scores', function (): void {
    expect(fn (): Score => Score::make(-1, 10))
        ->toThrow(InvalidArgumentException::class);
});

test('score rejects negative second player score', function (): void {
    expect(fn (): Score => Score::make(10, -1))
        ->toThrow(InvalidArgumentException::class);
});

test('score can be compared for equality', function (): void {
    $score1 = Score::make(11, 9);
    $score2 = Score::make(11, 9);
    $score3 = Score::make(11, 10);

    expect($score1->equals($score2))->toBeTrue();
    expect($score1->equals($score3))->toBeFalse();
});

test('score converts to string', function (): void {
    $score = Score::make(11, 9);
    expect((string) $score)->toBe('11-9');
});

test('score handles zero scores', function (): void {
    $score = Score::make(0, 0);
    expect($score->player1())->toBe(0);
    expect($score->player2())->toBe(0);
    expect($score->isWon())->toBeFalse();
});

test('score handles high scores', function (): void {
    $score = Score::make(27, 25);
    expect($score->winner())->toBe(1);
    expect($score->difference())->toBe(2);
});

test('score handles deuce scenario', function (): void {
    $score = Score::make(12, 10);
    expect($score->winner())->toBe(1);
    expect($score->maxScore())->toBe(12);
});
