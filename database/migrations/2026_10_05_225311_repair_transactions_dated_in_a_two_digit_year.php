<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bank import n° 19 (28 September 2026) read « 22-09-26 » as Y-m-d and filed
 * fifteen lines in the year 22. The year it holds is the day of the month,
 * and the day it holds is the year within the century: swap them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')
            ->where('date', '<', '0100-01-01')
            ->orderBy('id')
            ->each(function (object $row): void {
                [$day, $month, $year] = array_map(intval(...), explode('-', substr((string) $row->date, 0, 10)));

                DB::table('transactions')->where('id', $row->id)->update([
                    'date' => sprintf('%04d-%02d-%02d', 2000 + $year, $month, $day),
                ]);
            });
    }
};
