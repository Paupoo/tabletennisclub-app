<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use Carbon\Carbon;

class GeneratePaymentReference
{
    public string $reference;

    private readonly string $date;

    private readonly Carbon $now;

    private string $sequence;

    private string $verification;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->now = Carbon::now();
        $this->date = $this->now->copy()->format('dmy');
        $this->sequence = $this->getNextSequence();
        $this->reference = '0' . $this->date . $this->sequence;
        $this->verification = $this->getCheckSum();
    }

    public function __invoke(): string
    {
        $sequence = (int) $this->sequence;

        do {
            $this->sequence = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $this->reference = '0' . $this->date . $this->sequence;
            $this->verification = $this->getCheckSum();
            $reference = $this->addSeparators($this->reference . $this->verification);
            $sequence++;
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Add the 2 '/' after the 3rd and the 7th number
     */
    public function addSeparators(string $string): string
    {
        $string = substr_replace($string, '/', 7, 0);

        return substr_replace($string, '/', 3, 0);
    }

    /**
     * Format a modulo-97 remainder as the 2-digit check number per the Belgian
     * structured communication standard: always zero-padded, and 0 becomes 97
     * ("00" is not a valid check number).
     */
    private function formatCheckDigits(int $remainder): string
    {
        return str_pad((string) ($remainder === 0 ? 97 : $remainder), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the 2-digit Belgian OGM/VCS check number for the current reference base.
     */
    private function getCheckSum(): string
    {
        return $this->formatCheckDigits((int) $this->reference % 97);
    }

    /**
     * Get the next sequence of the day
     */
    private function getNextSequence(): string
    {
        $todayPaymentCount = (int) Payment::whereBetween('created_at', [
            $this->now->copy()->startOfDay(),
            $this->now->copy()->endOfDay(),
        ])->count();

        $todayPaymentCount++;

        return str_pad((string) $todayPaymentCount, 3, '0', STR_PAD_LEFT);
    }
}
