<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Foundation\Mix;

class GeneratePaymentReference
{
    private Carbon $now;
    private string $date;
    private string $sequence;
    public string $reference;
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
        $string = (string) $this->reference . $this->verification;
        return $this->addSeperators($string);
    }

    /**
     * Get the next sequence of the day
     * @return string
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
    
    /**
     * Returns the validation number
     * @return int
     */
    private function getCheckSum(): int
    {
        return (int) $this->reference % 97;
    }

    /**
     * Add the 2 '/' after the 3rd and the 7th number 
     * @param string $string
     * @return array|string
     */
    public function addSeperators(string $string): string
    {
        $string = substr_replace($string, '/', 7, 0);
        $string = substr_replace($string, '/', 3, 0);
        return $string;
    }
}
