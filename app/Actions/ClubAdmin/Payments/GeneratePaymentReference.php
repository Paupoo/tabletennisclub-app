<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Models\ClubAdmin\Payment\Payment;
use Carbon\Carbon;

class GeneratePaymentReference
{
    public string $reference;

    private string $date;

    private Carbon $now;

    private string $sequence;

    private int $verification;

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
        $string = substr_replace($string, '/', 3, 0);

        return $string;
    }

    /**
     * Returns the validation number
     */
    private function getCheckSum(): int
    {
        return (int) $this->reference % 97;
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

        $todayPaymentCount = str_pad((string) $todayPaymentCount, 3, '0', STR_PAD_LEFT);

        return (string) $todayPaymentCount;
    }
}
