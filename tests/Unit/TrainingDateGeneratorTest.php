<?php

namespace Tests\Unit;

use App\Enums\Recurrence;
use App\Services\TrainingDateGenerator;
use Carbon\Carbon;
use Exception;
use PHPUnit\Framework\TestCase;

class TrainingDateGeneratorTest extends TestCase
{

    public function test_no_occurence_positive_test_case(): void
    {
        $start_date = '2024-08-14';
        $recurrence = Recurrence::NONE->name;

        $expected_answer = [
            Carbon::parse($start_date),
        ];

        $date_generator = new TrainingDateGenerator();
        $this->assertEquals($expected_answer, $date_generator->generateDates($start_date, null, $recurrence));

    }

    public function test_no_occurence_with_unexpected_recurrence(): void
    {
        $start_date = '2024-08-14';
        $recurrence = Recurrence::DAILY->name;
        $date_generator = new TrainingDateGenerator();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf('The occurence cannot be set without an end date or it must be set to %s.', Recurrence::NONE->name));

        $date_generator->generateDates($start_date, null, $recurrence);
    }

    public function test_start_date_is_not_smaller_or_equal_to_end_date(): void
    {
        $start_date = '2024-08-17';
        $end_date = '1988-08-17';

        $date_generator = new TrainingDateGenerator();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf('The start date [%s] must be smaller or equal to the end date [%s] and vice-versa.', $start_date, $end_date));

        $date_generator->generateDates($start_date, $end_date, 7);
    }

    public function test_daily_recurrence_is_returning_expected_items(): void
    {
        // test on 5 days
        $expected_array = [
            Carbon::parse('2024-08-19'),
            Carbon::parse('2024-08-20'),
            Carbon::parse('2024-08-21'),
            Carbon::parse('2024-08-22'),
            Carbon::parse('2024-08-23'),
        ];

        $date_generator = new TrainingDateGenerator();
        $start_date = '2024-08-19';
        $end_date = '2024-08-23';
        $recurrence = Recurrence::DAILY->name;

        $array_of_dates = $date_generator->generateDates($start_date, $end_date, $recurrence);
        $total_dates = count($array_of_dates);

        $this->assertEquals(5, $total_dates);
        $this->assertEquals($expected_array, $array_of_dates);
    }

    public function test_weekly_currence_is_returning_expected_items(): void
    {
        // test on 4 weeks

        $expected_array = [
            Carbon::parse('2024-08-06'),
            Carbon::parse('2024-08-13'),
            Carbon::parse('2024-08-20'),
            Carbon::parse('2024-08-27'),
        ];

        $date_generator = new TrainingDateGenerator();
        $start_date = '2024-08-06';
        $end_date = '2024-08-30';
        $recurrence = Recurrence::WEEKLY->name;

        $array_of_dates = $date_generator->generateDates($start_date, $end_date, $recurrence);
        $total_dates = count($array_of_dates);

        $this->assertEquals(4, $total_dates);
        $this->assertEquals($expected_array, $array_of_dates);

    }

    public function test_biweekly_currence_is_returning_expected_items(): void
    {
        // test on 12 weeks (6 biweekly)
        $expected_array = [
            Carbon::parse('2024-08-06'),
            Carbon::parse('2024-08-20'),
            Carbon::parse('2024-09-03'),
            Carbon::parse('2024-09-17'),
            Carbon::parse('2024-10-01'),
            Carbon::parse('2024-10-15'),
        ];
        $date_generator = new TrainingDateGenerator();
        $start_date = '2024-08-06';
        $end_date = '2024-10-28';
        $recurrence = Recurrence::BIWEEKLY->name;

        $array_of_dates = $date_generator->generateDates($start_date, $end_date, $recurrence);
        $total_dates = count($array_of_dates);

        $this->assertEquals(6, $total_dates);
        $this->assertEquals($expected_array, $array_of_dates);

    }

    public function test_recurrence_not_allowed(): void
    {
        // test on 12 weeks (6 biweekly)
        $date_generator = new TrainingDateGenerator();
        $start_date = '2024-08-06';
        $end_date = '2024-08-06';
        $recurrence = 'notExpected';

        $this->expectException(Exception::class);

        $date_generator->generateDates($start_date, $end_date, $recurrence);
    }
}
