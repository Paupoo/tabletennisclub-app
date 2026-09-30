<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\Shared\Enums\ExpenseCategory;
use Illuminate\Support\Facades\DB;

function runExpenseCategoryMappingMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_30_000323_map_expense_report_categories_onto_the_nine_expense_categories.php');
    $migration->up();
}

it('files the old natures of expense reports under the nine expense categories', function (string $old, ExpenseCategory $new): void {
    $report = ExpenseReport::factory()->create();
    ExpenseReport::factory()->create();
    DB::table('expense_reports')->where('id', $report->id)->update(['category' => $old]);

    runExpenseCategoryMappingMigration();

    expect($report->refresh()->category)->toBe($new);
})->with([
    'sports equipment stays' => ['sports_equipment', ExpenseCategory::SportsEquipment],
    'hall equipment is sports equipment' => ['facilities', ExpenseCategory::SportsEquipment],
    'supplies are running costs' => ['administrative', ExpenseCategory::Operations],
    'an event stays an event' => ['event', ExpenseCategory::Event],
    'the bar stays the bar' => ['bar', ExpenseCategory::Bar],
    'travel stays travel' => ['travel', ExpenseCategory::Travel],
    'other stays other' => ['other', ExpenseCategory::Other],
]);
