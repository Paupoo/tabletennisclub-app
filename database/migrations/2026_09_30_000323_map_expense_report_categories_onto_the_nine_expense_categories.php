<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expense reports used their own seven natures; they now share the nine
 * expense categories of the club's accounts. Two natures had no category of
 * their own: hall equipment is sports equipment, supplies and administration
 * are running costs. The other five keep their stored value.
 */
return new class extends Migration
{
    /**
     * Reverse the migrations.
     *
     * Hall equipment is not brought back: nothing tells it apart from the
     * sports equipment it was merged into. The categories only reports filed
     * after the change can carry have no older nature to go back to.
     */
    public function down(): void
    {
        DB::table('expense_reports')->where('category', 'operations')->update(['category' => 'administrative']);
        DB::table('expense_reports')->whereIn('category', ['federation', 'hall', 'training'])->update(['category' => 'other']);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('expense_reports')->where('category', 'facilities')->update(['category' => 'sports_equipment']);
        DB::table('expense_reports')->where('category', 'administrative')->update(['category' => 'operations']);
    }
};
