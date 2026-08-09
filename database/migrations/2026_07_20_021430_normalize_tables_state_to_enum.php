<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\TableStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three vocabularies had been coexisting in `tables.state`:
 *   - the form wrote 'Good condition' / 'Needs repair' / 'Out of service'
 *   - the database held 'used' and NULL
 *   - TournamentTableService tested against 'oos'
 *
 * 'oos' matched nothing the form could produce, so a table flagged out of
 * service still counted as playable when sizing a tournament. Collapse
 * everything onto TableStateEnum and make the column non-nullable.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table): void {
            $table->string('state')->nullable()->default(null)->change();
        });

        DB::table('tables')
            ->where('state', TableStateEnum::OUT_OF_SERVICE->value)
            ->update(['state' => 'oos']);

        DB::table('tables')
            ->whereIn('state', [TableStateEnum::GOOD->value, TableStateEnum::NEEDS_REPAIR->value])
            ->update(['state' => 'used']);
    }

    public function up(): void
    {
        $map = [
            'Good condition' => TableStateEnum::GOOD->value,
            'Needs repair' => TableStateEnum::NEEDS_REPAIR->value,
            'Out of service' => TableStateEnum::OUT_OF_SERVICE->value,
            'oos' => TableStateEnum::OUT_OF_SERVICE->value,
            'used' => TableStateEnum::GOOD->value,
        ];

        foreach ($map as $legacy => $value) {
            DB::table('tables')->where('state', $legacy)->update(['state' => $value]);
        }

        // Rows never given a state, and anything left from a vocabulary we do
        // not know about: a table on the floor is assumed usable.
        DB::table('tables')
            ->whereNull('state')
            ->orWhereNotIn('state', TableStateEnum::values())
            ->update(['state' => TableStateEnum::GOOD->value]);

        Schema::table('tables', function (Blueprint $table): void {
            $table->string('state')->default(TableStateEnum::GOOD->value)->nullable(false)->change();
        });
    }
};
