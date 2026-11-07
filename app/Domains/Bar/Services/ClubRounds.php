<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\LeagueCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Le verre du club : la tournée que le club offre à une équipe visiteuse.
 *
 * La règle comptait par **club** : Braine-L'Alleud B et Braine-L'Alleud F, venues le
 * même soir, se partageaient un seul verre, et la seconde équipe se le voyait refuser.
 * Elle compte désormais par **équipe**, une fois par jour d'exploitation, avec un
 * plafond lu sur la division du match : quatre consommations en messieurs, trois en
 * vétérans et en dames, qui jouent à trois.
 *
 * Le comptoir et l'écran d'encaissement passent tous deux par ici, pour qu'une équipe
 * refusée d'un côté ne soit pas acceptée de l'autre.
 */
final class ClubRounds
{
    /**
     * Le plafond d'un bénéficiaire saisi à la main, et d'une équipe dont la division
     * ne dit pas la catégorie.
     */
    public const int DEFAULT_CAP = 4;

    /**
     * Le plafond des divisions qui alignent trois joueurs.
     */
    public const int SHORT_TEAM_CAP = 3;

    /**
     * Le jour d'exploitation du bar : de 6 h à 6 h, pour qu'une soirée qui déborde
     * après minuit reste une seule soirée.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function businessDay(CarbonInterface $moment): array
    {
        $start = Carbon::instance($moment)->startOfDay()->setTime(6, 0);

        if ($moment->lt($start)) {
            $start->subDay();
        }

        return [$start, $start->copy()->addDay()];
    }

    /**
     * Le plafond d'un verre du club selon la catégorie de la division.
     */
    public static function capFor(?LeagueCategory $category): int
    {
        return match ($category) {
            LeagueCategory::VETERANS, LeagueCategory::WOMEN => self::SHORT_TEAM_CAP,
            default => self::DEFAULT_CAP,
        };
    }

    /**
     * Pourquoi ce verre du club serait refusé, ou null s'il peut être servi.
     *
     * @param  CarbonInterface  $fixtureDay  le jour où l'on cherche le match (celui de la commande)
     * @param  CarbonInterface  $settledAt  le jour où le verre est servi, qui seul compte pour « une fois par jour »
     */
    public function refusal(int $articles, string $beneficiary, bool $manual, CarbonInterface $fixtureDay, CarbonInterface $settledAt): ?string
    {
        $beneficiary = trim($beneficiary);

        if ($beneficiary === '') {
            return 'Indiquez à qui va le verre du club.';
        }

        if ($manual) {
            return $articles > self::DEFAULT_CAP
                ? sprintf('Un verre du club ne peut pas contenir plus de %d articles.', self::DEFAULT_CAP)
                : null;
        }

        $team = $this->teamsFor($fixtureDay, $settledAt)
            ->first(fn (array $team): bool => BarOrder::normaliseName($team['name']) === BarOrder::normaliseName($beneficiary));

        if ($team === null) {
            return 'Sélectionnez une équipe visiteuse attendue aujourd’hui.';
        }

        if ($team['served_at'] !== '') {
            return 'Cette équipe a déjà reçu son verre du club aujourd’hui.';
        }

        if ($articles > $team['cap']) {
            return sprintf('Un verre du club ne peut pas contenir plus de %d articles pour cette équipe.', $team['cap']);
        }

        return null;
    }

    /**
     * Les équipes visiteuses du jour, une ligne par équipe et non par club.
     *
     * `served_at` dit à quelle heure l'équipe a déjà reçu son verre pendant le jour d'exploitation
     * de `$settledAt` : une commande ouverte la veille et réglée aujourd'hui compte
     * aujourd'hui.
     *
     * @return Collection<int, array{name: string, category: string, cap: int, served_at: string}>
     */
    public function teamsFor(CarbonInterface $fixtureDay, ?CarbonInterface $settledAt = null): Collection
    {
        [$fixtureStart, $fixtureEnd] = self::businessDay($fixtureDay);
        [$settledStart, $settledEnd] = self::businessDay($settledAt ?? $fixtureDay);

        $served = BarOrder::query()
            ->where('payment_method', 'offered')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $settledStart)
            ->where('paid_at', '<', $settledEnd)
            ->get(['reason', 'paid_at'])
            ->filter(fn (BarOrder $order): bool => is_string($order->reason) && $order->reason !== '')
            ->mapWithKeys(fn (BarOrder $order): array => [BarOrder::normaliseName($order->reason) => Carbon::parse($order->paid_at)]);

        $interclubs = Interclub::query()
            ->where('start_date_time', '>=', $fixtureStart)
            ->where('start_date_time', '<', $fixtureEnd)
            ->whereHas('visitedTeam.club', fn ($query) => $query->where('is_own_club', true))
            ->whereHas('visitingTeam.club', fn ($query) => $query->where('is_own_club', false))
            ->with(['visitingTeam.club', 'league'])
            ->get();

        $teams = [];

        foreach ($interclubs as $interclub) {
            $team = $interclub->visitingTeam;

            if ($team === null || $team->club === null) {
                continue;
            }

            $name = $team->fullName();
            $category = LeagueCategory::fromName($interclub->league?->category);
            $servedAt = $served->get(BarOrder::normaliseName($name));

            // Une ligne par équipe : la même équipe ne joue qu'un match par soir,
            // mais une double saisie ne doit pas la montrer deux fois.
            $teams[BarOrder::normaliseName($name)] = self::team($name, $category, $servedAt instanceof Carbon ? $servedAt : null);
        }

        uasort($teams, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return new Collection(array_values($teams));
    }

    /**
     * @return array{name: string, category: string, cap: int, served_at: string}
     */
    private static function team(string $name, ?LeagueCategory $category, ?Carbon $servedAt): array
    {
        return [
            'name' => $name,
            // Le libellé et non l'enum : l'écran n'en montre que le nom.
            'category' => $category?->label() ?? '',
            'cap' => self::capFor($category),
            // L'heure du verre déjà servi, vide sinon : l'écran l'affiche telle quelle.
            'served_at' => $servedAt?->format('H:i') ?? '',
        ];
    }
}
