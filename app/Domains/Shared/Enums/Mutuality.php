<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use Illuminate\Support\Str;

/**
 * The Belgian mutual insurers the club issues attestations for.
 *
 * Held in code rather than in a table because these are facts about the
 * outside world that deserve a reading in pull request: what each one caps its
 * intervention at, and — the load-bearing one — whether it says in writing that
 * a club-issued attestation is enough. MC and Mutualité Neutre both do; the
 * other three ask for their own form.
 *
 * The uploaded form itself is not here: that is an artefact, and it lives in
 * `attestation_templates`.
 */
enum Mutuality: string
{
    case MC = 'mc';
    case MutPlus = 'mutplus';
    case Neutral = 'neutral';
    case Other = 'other';
    case Partenamut = 'partenamut';
    case Solidaris = 'solidaris';

    /**
     * Reading order for a member: alphabetical, « une autre mutualité » last.
     *
     * Declaration order is alphabetical on the case names, which says nothing
     * to a reader: it puts MutPlus before Neutre because of how they are spelt
     * in code, and drops "Other" in the middle of the list. Accents are folded
     * so that « Mutualité Neutre » sorts under M rather than after Z.
     *
     * Mirrors Role::sortedByLabel(), for the same reason.
     *
     * @param  iterable<int, self>  $mutualities
     * @return array<int, self>
     */
    public static function inReadingOrder(iterable $mutualities): array
    {
        $sorted = is_array($mutualities) ? array_values($mutualities) : iterator_to_array($mutualities, false);

        usort($sorted, static function (self $a, self $b): int {
            // The catch-all belongs at the end whatever it is called: it is not
            // an insurer, it is what a member picks when theirs is not listed.
            if ($a === self::Other || $b === self::Other) {
                return ($a === self::Other ? 1 : 0) <=> ($b === self::Other ? 1 : 0);
            }

            return Str::lower(Str::ascii($a->label())) <=> Str::lower(Str::ascii($b->label()));
        });

        return $sorted;
    }

    /**
     * Those the club holds a camp form layout for.
     *
     * @return array<int, self>
     */
    public static function withCampForm(): array
    {
        return [self::Partenamut, self::Solidaris];
    }

    /**
     * Those the member can be handed a form for.
     *
     * @return array<int, self>
     */
    public static function withOfficialForm(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $mutuality): bool => $mutuality !== self::Other,
        ));
    }

    /**
     * Whether this insurer accepts a document written by the club itself.
     *
     * Only asserted where the form says so in as many words. Everywhere else
     * the answer is "we do not know", which reads as false: the club must not
     * send a substitute on a guess.
     *
     * For a camp the club's own attestation is offered once, under « another
     * mutual insurer », and names no recipient: that is what lets a family hand
     * it to an employer or a CPAS just as well.
     */
    public function acceptsClubAttestation(AttestationKind $kind = AttestationKind::Affiliation): bool
    {
        if ($kind === AttestationKind::Camp) {
            return $this === self::Other;
        }

        return match ($this) {
            self::MC, self::Neutral, self::Other => true,
            default => false,
        };
    }

    /**
     * What the insurer pays for a camp, and for whom — told, never enforced.
     *
     * The statutes change without notice, and an age written into a filter
     * would bar the one teenager who turned eighteen during the week.
     */
    public function campConditions(): ?string
    {
        return match ($this) {
            self::Partenamut => __('Up to 18 years old inclusive · up to :day € a day, 10 days and :year € a year. Their own form and the club\'s attestation are both accepted.', ['day' => '11,50', 'year' => 115]),
            self::Solidaris => __('Under 18 · :low to :high € a day, up to 25 days a year.', ['low' => 5, 'high' => 6]),
            default => null,
        };
    }

    /** The yearly ceiling, for what the member is told to expect. */
    public function cap(): ?string
    {
        return match ($this) {
            self::MC => __(':adults € per year for adults, :children € for children', ['adults' => 50, 'children' => 100]),
            self::MutPlus => null,
            self::Neutral => __(':amount € per calendar year', ['amount' => 50]),
            self::Other => null,
            self::Partenamut => null,
            self::Solidaris => __(':adults € per year for adults, :children € for children', ['adults' => 40, 'children' => 50]),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MC => 'Mutualité chrétienne (MC)',
            self::MutPlus => 'Mutualité Libérale — MutPlus.be',
            self::Neutral => 'Mutualité Neutre',
            self::Other => __('Another mutual insurer'),
            self::Partenamut => 'Partenamut',
            self::Solidaris => 'Solidaris Wallonie',
        };
    }

    /** The name a member picks it by, for the kind of document they are after. */
    public function labelFor(AttestationKind $kind): string
    {
        return $kind === AttestationKind::Camp && $this === self::Other
            ? __('The club\'s attestation — another mutual insurer, an employer, a CPAS…')
            : $this->label();
    }

    /** Where the member is told to send the completed document. */
    public function url(AttestationKind $kind = AttestationKind::Affiliation): ?string
    {
        if ($kind === AttestationKind::Camp) {
            return match ($this) {
                self::Partenamut => 'https://www.partenamut.be/fr/remboursements-avantages/enfants/stages-camps-sejours-scolaires',
                self::Solidaris => 'https://www.solidaris-wallonie.be',
                default => null,
            };
        }

        return match ($this) {
            self::MC => 'https://www.mc.be/avantages/sport',
            self::MutPlus => 'https://www.mutplus.be',
            self::Neutral => 'https://www.mutualiteneutre.be',
            self::Other => null,
            self::Partenamut => 'https://www.partenamut.be/fr/remboursements-avantages/club-sport',
            self::Solidaris => 'https://www.solidaris.be',
        };
    }
}
