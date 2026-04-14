<?php

use App\View\Components\WeekCard;

pest()->group('components', 'weekCard');

it('détermine correctement le statut basé sur le score', function (array $score, string $expectedStatus) {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        score: $score
    );

    expect($component->status)->toBe($expectedStatus);
})->with([
    'victoire' => [['home' => 10, 'away' => 5], 'win'],
    'défaite'  => [['home' => 5, 'away' => 10], 'loss'],
    'nul'      => [['home' => 7, 'away' => 7], 'draw'],
]);

it('utilise le statut fourni explicitement au lieu de le calculer', function () {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        score: ['home' => 10, 'away' => 5], // Serait normalement 'win'
        status: 'pending'
    );

    expect($component->status)->toBe('pending');
});

it('est futur si aucun score n est fourni', function () {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        score: null
    );

    expect($component->status)->toBe('future');
});

it('vérifie si le composant est extensible', function (string $status, ?array $matches, bool $expected) {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        status: $status,
        matches: $matches
    );

    expect($component->isExpandable())->toBe($expected);
})->with([
    'win avec matches'     => ['win', ['match1'], true],
    'win sans matches'     => ['win', [], false],
    'future avec matches'  => ['future', ['match1'], false],
    'loss avec matches'    => ['loss', ['match1'], true],
]);

it('retourne les bonnes classes CSS selon le statut', function (string $status, string $barColor, string $dotStyle) {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        status: $status
    );

    expect($component->barColor())->toBe($barColor);
    expect($component->dotStyle())->toBe($dotStyle);
})->with([
    'status win'     => ['win', 'bg-success', 'bg-success'],
    'status loss'    => ['loss', 'bg-error', 'bg-error'],
    'status pending' => ['pending', 'bg-warning', 'border-2 border-warning'],
    'status default' => ['unknown', 'bg-base-300', 'border border-base-300'],
]);

it('calcule la classe CSS du score pour l équipe à domicile', function (string $status, string $expectedClass) {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        status: $status
    );

    expect($component->scoreHomeClass())->toBe($expectedClass);
})->with([
    'victoire' => ['win', 'bg-success/15 text-success'],
    'défaite'  => ['loss', 'bg-error/15 text-error'],
    'autre'    => ['draw', 'bg-base-200 text-base-content'],
]);

it('renvoie les bonnes données à la vue lors du rendu', function () {
    $component = new WeekCard(
        week: 1,
        opponent: 'Team A',
        date: '2024-10-10',
        score: ['home' => 3, 'away' => 0]
    );

    $view = $component->render();
    $data = $view->getData();

    expect($data['status'])->toBe('win');
    expect($data['barColor'])->toBe('bg-success');
    expect($data['isExpandable'])->toBeFalse(); // Car matches est null par défaut
});