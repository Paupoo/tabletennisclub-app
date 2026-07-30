<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Users;

use App\Data\User\FederationRow;
use App\Data\User\SharedAddressDecision;
use Illuminate\Support\Str;

/**
 * Settles who keeps an email address when several affiliates were listed under
 * the same one.
 *
 * A parent enrolling their children is recorded by the federation with their own
 * mailbox against each child. An address identifies a login, so exactly one of
 * them may keep it; the children are reached through a guardian instead.
 *
 * Whoever loses the address is not losing anything they had: they never had a
 * login, and the club can hand them one the day they have an address of their own.
 */
class SharedAddressResolver
{
    /**
     * @param  array<int, FederationRow>  $rows
     * @return array<int, SharedAddressDecision> Keyed by line number; only the
     *                                           affiliates sharing an address appear.
     */
    public function resolve(array $rows): array
    {
        $decisions = [];

        foreach ($this->clusters($rows) as $cluster) {
            foreach ($this->resolveCluster($cluster) as $lineNumber => $decision) {
                $decisions[$lineNumber] = $decision;
            }
        }

        return $decisions;
    }

    /**
     * Affiliates grouped by the address they were listed under, keeping only the
     * addresses carrying more than one of them.
     *
     * @param  array<int, FederationRow>  $rows
     * @return array<string, array<int, FederationRow>>
     */
    private function clusters(array $rows): array
    {
        $byAddress = [];

        foreach ($rows as $row) {
            if ($row->email === null) {
                continue;
            }

            $byAddress[$this->normalize($row->email)][] = $row;
        }

        return array_filter($byAddress, static fn (array $cluster): bool => count($cluster) > 1);
    }

    /**
     * An affiliate the club may treat as answering for themselves. A missing
     * birthdate is not proof of childhood, so it counts as adult — but it never
     * wins the address over someone whose age is known.
     */
    private function isAdult(FederationRow $row): bool
    {
        return $row->birthdate === null || $row->birthdate->age >= 18;
    }

    private function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * @param  array<int, FederationRow>  $cluster
     * @return array<int, SharedAddressDecision>
     */
    private function resolveCluster(array $cluster): array
    {
        $adults = array_values(array_filter($cluster, fn (FederationRow $row): bool => $this->isAdult($row)));

        if ($adults === []) {
            return $this->resolveWithoutAdult($cluster);
        }

        $keeper = $this->seniorMost($adults);
        $decisions = [];

        foreach ($cluster as $row) {
            if ($row->lineNumber === $keeper->lineNumber) {
                $decisions[$row->lineNumber] = new SharedAddressDecision(keepsEmail: true);

                continue;
            }

            $decisions[$row->lineNumber] = new SharedAddressDecision(
                keepsEmail: false,
                // Only a child is handed a guardian. Two adults on one address are
                // a couple, and a couple is not a guardianship: the one who loses
                // the address is asked for their own, by a human.
                guardianLineNumber: $this->isAdult($row) ? null : $keeper->lineNumber,
            );
        }

        return $decisions;
    }

    /**
     * Children only, so the address belongs to a parent who does not play. The
     * club records them as a guardian without a member account of their own.
     *
     * @param  array<int, FederationRow>  $cluster
     * @return array<int, SharedAddressDecision>
     */
    private function resolveWithoutAdult(array $cluster): array
    {
        $email = $cluster[0]->email;
        $phone = null;

        foreach ($cluster as $row) {
            $phone ??= $row->phone;
        }

        $decisions = [];

        foreach ($cluster as $row) {
            $decisions[$row->lineNumber] = new SharedAddressDecision(
                keepsEmail: false,
                externalGuardian: true,
                guardianEmail: $email,
                guardianPhone: $phone,
            );
        }

        return $decisions;
    }

    /**
     * The eldest keeps the address — on a family address that is the parent.
     * Affiliates of unknown age come last, and line order breaks any tie so the
     * same file always yields the same decision.
     *
     * @param  array<int, FederationRow>  $adults
     */
    private function seniorMost(array $adults): FederationRow
    {
        usort($adults, static function (FederationRow $a, FederationRow $b): int {
            if ($a->birthdate === null || $b->birthdate === null) {
                return ($a->birthdate === null ? 1 : 0) <=> ($b->birthdate === null ? 1 : 0)
                    ?: $a->lineNumber <=> $b->lineNumber;
            }

            return $a->birthdate <=> $b->birthdate
                ?: $a->lineNumber <=> $b->lineNumber;
        });

        return $adults[0];
    }
}
