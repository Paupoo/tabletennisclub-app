<?php

declare(strict_types=1);
use App\Enums\Recurrence;
use App\Services\TrainingDateGenerator;
use Carbon\Carbon;

test('biweekly currence is returning expected items', function () {
    // test on 12 weeks (6 biweekly)
    $expected_array = [
        Carbon::parse('2024-08-06'),
        Carbon::parse('2024-08-20'),
        Carbon::parse('2024-09-03'),
        Carbon::parse('2024-09-17'),
        Carbon::parse('2024-10-01'),
        Carbon::parse('2024-10-15'),
    ];
    $date_generator = new TrainingDateGenerator;
    $start_date = '2024-08-06';
    $end_date = '2024-10-28';
    $recurrence = Recurrence::BIWEEKLY->name;

    $array_of_dates = $date_generator->generateDates($start_date, $end_date, $recurrence);
    $total_dates = count($array_of_dates);

    expect($total_dates)->toEqual(6);
    expect($array_of_dates)->toEqual($expected_array);
});
test('daily recurrence is returning expected items', function () {
    // test on 5 days
    $expected_array = [
        Carbon::parse('2024-08-19'),
        Carbon::parse('2024-08-20'),
        Carbon::parse('2024-08-21'),
        Carbon::parse('2024-08-22'),
        Carbon::parse('2024-08-23'),
    ];

    $date_generator = new TrainingDateGenerator;
    $start_date = '2024-08-19';
    $end_date = '2024-08-23';
    $recurrence = Recurrence::DAILY->name;

    $array_of_dates = $date_generator->generateDates($start_date, $end_date, $recurrence);
    $total_dates = count($array_of_dates);

    expect($total_dates)->toEqual(5);
    expect($array_of_dates)->toEqual($expected_array);
});
test('exception is returned when no endate with recurrent', function () {
    $date_generator = new TrainingDateGenerator;
    $start_date = '2024-08-06';
    $end_date = null;
    $recurrence = '7';

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('The occurence cannot be set without an end date or it must be set to NONE.');

    $date_generator->generateDates($start_date, $end_date, $recurrence);
});
test('no occurence positive test case', function () {
    $start_date = '2024-08-14';
    $recurrence = Recurrence::NONE->name;

    $expected_answer = [
        Carbon::parse($start_date),
    ];

    $date_generator = new TrainingDateGenerator;
    expect($date_generator->generateDates($start_date, null, $recurrence))->toEqual($expected_answer);
});
test('no occurence with unexpected recurrence', function () {
    $start_date = '2024-08-14';
    $recurrence = Recurrence::DAILY->name;
    $date_generator = new TrainingDateGenerator;

    $this->expectException(Exception::class);
    $this->expectExceptionMessage(sprintf('The occurence cannot be set without an end date or it must be set to %s.', Recurrence::NONE->name));

    $date_generator->generateDates($start_date, null, $recurrence);
});
test('recurrence not existing in enum returns an exception', function () {
    $date_generator = new TrainingDateGenerator;
    $start_date = '2024-08-06';
    $end_date = '2024-08-06';
    $recurrence = 'notExpected';

    $this->expectException(InvalidArgumentException::class);

    $date_generator->generateDates($start_date, $end_date, $recurrence);
});
test('start date is not smaller or equal to end date', function () {
    $start_date = '2024-08-17';
    $end_date = '1988-08-17';

    $date_generator = new TrainingDateGenerator;
    $recurrence = Recurrence::WEEKLY->value;

    $this->expectException(Exception::class);
    $this->expectExceptionMessage(sprintf('The start date [%s] must be smaller or equal to the end date [%s] and vice-versa.', $start_date, $end_date));

    $date_generator->generateDates($start_date, $end_date, $recurrence);
});
test('weekly currence is returning expected items', function () {
    // test on 4 weeks
    $expected_array = [
        Carbon::parse('2024-08-06'),
        Carbon::parse('2024-08-13'),
        Carbon::parse('2024-08-20'),
        Carbon::parse('2024-08-27'),
    ];

    $date_generator = new TrainingDateGenerator;
    $start_date = '2024-08-06';
    $end_date = '2024-08-30';
    $recurrence = Recurrence::WEEKLY->name;

    $array_of_dates = $date_generator->generateDates($start_date, $end_date, $recurrence);
    $total_dates = count($array_of_dates);

    expect($total_dates)->toEqual(4);
    expect($array_of_dates)->toEqual($expected_array);
});
