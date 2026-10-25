<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What the club certifies: a season's affiliation, or one training camp.
 *
 * The insurers treat them as two different benefits with two different forms,
 * and the club certifies two different sums: the affiliation's money never
 * includes a camp, which is invoiced on its own line.
 */
enum AttestationKind: string
{
    case Affiliation = 'affiliation';
    case Camp = 'camp';

    public function label(): string
    {
        return match ($this) {
            self::Affiliation => __('Affiliation'),
            self::Camp => __('Training camp'),
        };
    }

    /**
     * ATT for an affiliation, STG for a stage: an insurer's desk reading the
     * reference over the phone knows at once which document it is holding.
     */
    public function referencePrefix(): string
    {
        return match ($this) {
            self::Affiliation => 'ATT',
            self::Camp => 'STG',
        };
    }
}
