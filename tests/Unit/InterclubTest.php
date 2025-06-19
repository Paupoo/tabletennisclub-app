<?php

declare(strict_types=1);
use App\Enums\LeagueCategory;
use App\Models\Interclub;

test('method set total players per team positive tests', function (): void {
    $interclub = new Interclub;

    $interclub->setTotalPlayersPerteam(LeagueCategory::MEN->name);
    expect($interclub->total_players)->toEqual(4);

    $interclub->setTotalPlayersPerteam(LeagueCategory::WOMEN->name);
    expect($interclub->total_players)->toEqual(3);

    $interclub->setTotalPlayersPerteam(LeagueCategory::VETERANS->name);
    expect($interclub->total_players)->toEqual(3);
});
test('method set total players per team with invalid string', function (): void {
    $interclub = new Interclub;

    $this->expectException('Exception');
    $this->expectExceptionMessage('This category is unknown and not allowed.');

    $interclub->setTotalPlayersPerteam('Homme');
});
test('method set total players per team without empty string', function (): void {
    $interclub = new Interclub;

    $this->expectException('Exception');
    $this->expectExceptionMessage('This category is unknown and not allowed.');
    $interclub->setTotalPlayersPerteam('');
});
test('method set week number positive tests', function (): void {
    $interclub = new Interclub;

    $interclub->setWeekNumber('2024-08-21');
    expect($interclub->week_number)->toEqual(34);

    $interclub->setWeekNumber('2025-03-23');
    expect($interclub->week_number)->toEqual(12);
});
test('method set week number wrong date', function (): void {
    $interclub = new Interclub;

    $this->expectException('Exception');
    $interclub->setWeekNumber('202E-08-17');
});
