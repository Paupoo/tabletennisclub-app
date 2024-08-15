<?php

namespace App\Services;

use App\Enums\Recurrence;
use Carbon\Carbon;
use Exception;

class TrainingDateGenerator
{
    /**
     * Return date objects based on the recurrence (every [x] days)
     *
     * @param string $start_date
     * @param string $end_date
     * @param string $recurrence
     * @return array<int, Carbon>
     * @throws
     */
    public function generateDates(string $start_date, string $end_date = null, string $recurrence): array
    {

        if($end_date === null && $recurrence !== Recurrence::NONE->name) {
            throw new Exception(sprintf('The occurence cannot be set without an end date or it must be set to s%.', Recurrence::NONE->name));
        }

        $training_dates = [];
        
        // Get reference dates
        $start_date = Carbon::parse($start_date);
        $end_date = Carbon::parse($end_date);

        // get recurrences
        $recurrence = match ($recurrence) {
            Recurrence::NONE->name => 0,
            Recurrence::DAILY->name => 1,
            Recurrence::WEEKLY->name => 7,
            Recurrence::BIWEEKLY->name => 14,
        };

        // fill the array with dates
        $training_dates[] = $start_date;

        if ($recurrence !== 0) {
            $next_training_date = $start_date->copy()->addDays($recurrence);
            while ($next_training_date <= $end_date) {
                $training_dates[] = $next_training_date->copy();
                $next_training_date = $next_training_date->addDays($recurrence);
            }
        }

        return $training_dates;
    }
        
}