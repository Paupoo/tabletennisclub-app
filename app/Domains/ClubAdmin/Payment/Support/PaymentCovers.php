<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Support;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * What a subscription payment bills, recorded when it is created.
 *
 * Pack names are kept as they were then: a pack renamed or deleted later must
 * not rewrite what an old payment asked for.
 *
 * Shape: {affiliation: bool, reason: null|"formula_change"|"pack_change",
 *         training_packs: list<{id: int, name: string}>}
 */
final class PaymentCovers
{
    public const string FORMULA_CHANGE = 'formula_change';

    public const string PACK_CHANGE = 'pack_change';

    /**
     * The first payment of a subscription: the affiliation, and the packs the
     * member is enrolled in at that moment.
     *
     * @return array{affiliation: bool, reason: null, training_packs: list<array{id: int, name: string}>}
     */
    public static function affiliation(Subscription $subscription): array
    {
        $packs = $subscription->trainingPacks()->wherePivot('status', 'enrolled')->orderBy('training_packs.id')->get();

        return ['affiliation' => true, 'reason' => null, 'training_packs' => self::describe($packs)];
    }

    /**
     * The complement a formula change calls for.
     *
     * @return array{affiliation: bool, reason: string, training_packs: list<array{id: int, name: string}>}
     */
    public static function formulaChange(): array
    {
        return ['affiliation' => true, 'reason' => self::FORMULA_CHANGE, 'training_packs' => []];
    }

    /**
     * The label a payment shows — in the invitation mail, its reminders, the
     * treasury and the member's payments.
     *
     * @param  array<string, mixed>  $covers
     * @return array{type: string, name: string}
     */
    public static function label(array $covers, Subscription $subscription): array
    {
        // The same season name the affiliation label always showed.
        $season = $subscription->getPaymentLabel()['name'];
        $packs = collect($covers['training_packs'] ?? [])->pluck('name')->filter()->implode(', ');
        $reason = $covers['reason'] ?? null;

        if ($covers['affiliation'] ?? false) {
            $name = $season . ($packs !== '' ? ' + ' . $packs : '');

            return [
                'type' => __('Subscription'),
                'name' => $reason === self::FORMULA_CHANGE ? __(':name (change of formula)', ['name' => $name]) : $name,
            ];
        }

        if ($packs === '') {
            return $subscription->getPaymentLabel();
        }

        return [
            'type' => count($covers['training_packs']) > 1 ? __('Training packs') : __('Training pack'),
            'name' => $reason === self::PACK_CHANGE ? __(':name (change of pack)', ['name' => $packs]) : $packs,
        ];
    }

    /**
     * Packs added to a subscription, or the one a member moved to.
     *
     * @param  iterable<TrainingPack>  $packs
     * @return array{affiliation: bool, reason: string|null, training_packs: list<array{id: int, name: string}>}
     */
    public static function packs(iterable $packs, ?string $reason = null): array
    {
        return ['affiliation' => false, 'reason' => $reason, 'training_packs' => self::describe($packs)];
    }

    /**
     * @param  iterable<TrainingPack>  $packs
     * @return list<array{id: int, name: string}>
     */
    private static function describe(iterable $packs): array
    {
        return collect($packs)
            ->map(fn (TrainingPack $pack): array => ['id' => $pack->id, 'name' => (string) $pack->name])
            ->values()
            ->all();
    }
}
