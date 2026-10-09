<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExternalParticipants\Services;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Forgets who a non-member was, once the club no longer needs to know.
 *
 * Six months after the event ended: long enough to chase a late payment or
 * answer a question about the stage, short enough that the club does not keep
 * a file of people who are not its members.
 *
 * A registration with money still open waits: erasing it would lose who owes
 * the club, or whom the club owes. It shows in the treasurer's tasks instead.
 * The money itself is never touched — the accounts must be kept — and neither
 * is the bank line, which carries what the bank printed.
 */
final class ExternalParticipantErasure
{
    /** How long after the event a non-member's identity is kept. */
    public const int MONTHS_KEPT = 6;

    /**
     * Registrations whose event ended long enough ago, not erased yet.
     *
     * @return Builder<ExternalRegistration>
     */
    public function due(): Builder
    {
        $cutoff = today()->subMonths(self::MONTHS_KEPT)->toDateString();

        return ExternalRegistration::query()
            ->whereNull('anonymized_at')
            ->whereHasMorph('registrable', [TrainingPack::class], fn (Builder $stage): Builder => $stage
                ->whereDate('pack_end_date', '<=', $cutoff));
    }

    /**
     * Due, but held back by money still open.
     *
     * @return Builder<ExternalRegistration>
     */
    public function heldBack(): Builder
    {
        return $this->due()->whereHas('payments', $this->openMoney(...));
    }

    /**
     * @return int how many registrations were erased
     */
    public function run(): int
    {
        $erased = 0;

        $this->due()
            ->whereDoesntHave('payments', $this->openMoney(...))
            ->orderBy('id')
            ->each(function (ExternalRegistration $registration) use (&$erased): void {
                $this->erase($registration);
                $erased++;
            });

        return $erased;
    }

    private function erase(ExternalRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $registration->update([
                'first_name' => null,
                'last_name' => null,
                'email' => null,
                'phone' => null,
                'guardian_first_name' => null,
                'guardian_last_name' => null,
                'guardian_phone' => null,
                'anonymized_at' => now(),
            ]);

            // The account a refund went to names somebody too; the bank line
            // keeps what the bank printed, which is the record that counts.
            $registration->payments()->whereNotNull('refund_iban')->update(['refund_iban' => null]);
        });
    }

    /**
     * An invoice still unpaid, or a refund not yet wired.
     *
     * @param  Builder<Payment>  $payments
     */
    private function openMoney(Builder $payments): void
    {
        $payments->where(fn (Builder $open): Builder => $open
            ->where(fn (Builder $claim): Builder => $claim
                ->where('status', 'pending')
                ->where(fn (Builder $method): Builder => $method->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
                ->whereColumn('amount_paid', '<', 'amount_due'))
            ->orWhere('status', 'to_refund'));
    }
}
