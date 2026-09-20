<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Services;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\MatchStrength;
use App\Domains\Shared\Support\IbanNormalizer;
use Illuminate\Support\Collection;

/**
 * Ce qui rapproche un paiement d'une ligne de relevé bancaire.
 *
 * Le rapprochement automatique en masse ne sait faire qu'une chose : référence
 * structurée + montant. Tout ce qu'il ne résout pas atterrit dans la modale
 * manuelle, où le trésorier se retrouve devant dix virements du même montant.
 * Ce barème existe pour distinguer ces dix-là.
 */
final class TransactionMatcher
{
    /**
     * Les candidates, notées et remises dans l'ordre où le trésorier veut les lire.
     *
     * L'unicité du montant se décide ici, et nulle part ailleurs : elle est une
     * propriété de l'ensemble proposé, pas d'une ligne isolée. Chaque transaction
     * repart avec un `match` posé dessus.
     *
     * @param  Collection<int, Transaction>  $candidates
     * @return Collection<int, Transaction>
     */
    public function rank(Payment $payment, Collection $candidates): Collection
    {
        $countByAmount = $candidates->countBy(fn (Transaction $t): string => (string) abs($t->amount));

        return $candidates
            ->map(function (Transaction $t) use ($payment, $countByAmount): Transaction {
                $t->match = $this->score($payment, $t, $countByAmount[(string) abs($t->amount)] === 1);

                return $t;
            })
            ->sortByDesc(fn (Transaction $t): int => $t->match->strength->rank())
            ->values();
    }

    /**
     * @param  bool  $amountIsUnique  Le montant ne vaut comme signal que s'il
     *                                désigne une seule candidate : un critère
     *                                qui s'allume partout n'informe de rien.
     */
    public function score(Payment $payment, Transaction $transaction, bool $amountIsUnique): TransactionMatch
    {
        // La référence structurée est un identifiant que le club a lui-même
        // émis : elle tranche seule, sans le concours d'aucun autre signal.
        if ($this->referenceMatches($payment, $transaction)) {
            return new TransactionMatch(MatchStrength::STRONG, [__('Structured reference')]);
        }

        $member = $this->payer($payment);

        if ($member === null) {
            return new TransactionMatch(MatchStrength::NONE);
        }

        $counterparty = $this->normalize($transaction->counterparty_name ?? '');
        $communication = [
            $this->normalize($transaction->free_reference ?? ''),
            $this->normalize($transaction->description ?? ''),
        ];

        $reasons = [];
        $strongHits = 0;

        $account = IbanNormalizer::normalize($transaction->counterparty_bank_account);

        if ($account !== null && IbanNormalizer::normalize($member->iban) === $account) {
            $reasons[] = __('Member IBAN');
            $strongHits++;
        }

        if ($this->namesMatch([$counterparty], $member->first_name, $member->last_name)) {
            $reasons[] = __('Member name on the counterparty');
            $strongHits++;
        } elseif ($this->surnameMatches([$counterparty], $member->last_name)) {
            $reasons[] = __('Member surname on the counterparty');
        }

        if ($this->namesMatch($communication, $member->first_name, $member->last_name)) {
            $reasons[] = __('Member name in the communication');
            $strongHits++;
        } elseif ($this->surnameMatches($communication, $member->last_name)) {
            $reasons[] = __('Member surname in the communication');
        }

        // Chaque tuteur est testé : « le tuteur » ne veut rien dire quand il y
        // en a deux, donc la raison le nomme.
        foreach ($member->guardians as $guardian) {
            if ($account !== null && IbanNormalizer::normalize($guardian->iban) === $account) {
                $reasons[] = __(':name (guardian) IBAN', ['name' => $guardian->full_name]);
                $strongHits++;
            }

            if ($this->namesMatch([$counterparty], $guardian->first_name, $guardian->last_name)) {
                $reasons[] = __(':name (guardian) on the counterparty', ['name' => $guardian->full_name]);
                $strongHits++;
            }
        }

        // Un montant qui désigne une seule candidate est une information ; le
        // même montant sur dix lignes n'en est pas une, et la place qu'il prend
        // est celle du signal qui, lui, distingue.
        if ($amountIsUnique && abs(abs($transaction->amount) - $payment->amount_due) < 0.01) {
            $reasons[] = __('Only candidate with this amount');
        }

        $strength = match (true) {
            $strongHits >= 2 => MatchStrength::STRONG,
            $strongHits === 1 => MatchStrength::TO_VERIFY,
            $reasons !== [] => MatchStrength::WEAK,
            default => MatchStrength::NONE,
        };

        return new TransactionMatch($strength, $reasons);
    }

    /** Une référence structurée se compare sur ses chiffres : le reste est de la mise en forme. */
    private function digits(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    /**
     * Prénom *et* nom présents dans un même texte.
     *
     * @param  list<string>  $haystacks
     */
    private function namesMatch(array $haystacks, string $firstName, string $lastName): bool
    {
        $first = $this->normalize($firstName);
        $last = $this->normalize($lastName);

        if ($first === '' || $last === '') {
            return false;
        }

        return array_any($haystacks, fn (string $haystack): bool => str_contains($haystack, $first) && str_contains($haystack, $last));
    }

    /**
     * Forme comparable : sans accents, sans casse, sans rien qui ne soit une lettre.
     *
     * Les espaces tombent avec le reste, et c'est le point : « Van De Ponseele »
     * et « VAN DE PONSEELE » deviennent tous deux `VANDEPONSEELE`. Les particules
     * sont le premier piège des noms belges, et tout découpage sur l'espace les
     * rate — le nom n'est pas un jeton, c'est une suite de lettres.
     */
    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtoupper(preg_replace('/[^a-zA-Z]/', '', $ascii ?: '') ?? '');
    }

    /**
     * Le membre derrière un paiement, quand il y en a un.
     *
     * `payable` est polymorphe et tous les payables ne désignent pas quelqu'un :
     * une commande de bar n'a pas de payeur identifié. Le contrat
     * Le contrat DescribesPayment n'expose qu'un nom, pas le modèle, d'où
     * cette résolution explicite.
     */
    private function payer(Payment $payment): ?User
    {
        $payable = $payment->payable;

        return match (true) {
            $payable instanceof Subscription,
            $payable instanceof TournamentRegistration,
            $payable instanceof MeetingUser => $payable->user,
            default => null,
        };
    }

    private function referenceMatches(Payment $payment, Transaction $transaction): bool
    {
        $expected = $this->digits($payment->reference);
        $found = $this->digits($transaction->structured_reference ?? '');

        return $expected !== '' && $expected === $found;
    }

    /**
     * Nom de famille seul : le cas du parent qui paie sous son propre nom.
     *
     * Deux lettres de trop et « MARTIN » matcherait n'importe quoi : on exige
     * quatre caractères, sous lesquels un patronyme ne discrimine plus rien.
     *
     * @param  list<string>  $haystacks
     */
    private function surnameMatches(array $haystacks, string $lastName): bool
    {
        $last = $this->normalize($lastName);

        if (strlen($last) < 4) {
            return false;
        }

        return array_any($haystacks, fn (string $haystack): bool => str_contains($haystack, $last));
    }
}
