<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->date('pack_start_date')->nullable()->change();
            $table->date('pack_end_date')->nullable()->change();
        });
    }

    /**
     * Make a training pack always declare the period it covers.
     *
     * A pack without dates was never ambiguous in practice: generateSessions()
     * already bounded it on its season, which is why the season-long packs
     * generate from September to June while the summer camp — the only one
     * carrying dates — runs in July, outside its own season. Only the pro rata
     * refused to read that same season, so a dateless pack could never be
     * billed for the months actually held and the treasurer had to force the
     * amount by hand (#48).
     *
     * Backfilling from the season therefore records what each pack already did;
     * it changes no schedule and no existing session.
     */
    public function up(): void
    {
        DB::table('training_packs')
            ->select('training_packs.id', 'training_packs.pack_start_date', 'training_packs.pack_end_date', 'seasons.start_at', 'seasons.end_at')
            ->join('seasons', 'seasons.id', '=', 'training_packs.season_id')
            ->where(function ($query): void {
                $query->whereNull('training_packs.pack_start_date')
                    ->orWhereNull('training_packs.pack_end_date');
            })
            ->orderBy('training_packs.id')
            ->get()
            ->each(function (object $pack): void {
                DB::table('training_packs')
                    ->where('id', $pack->id)
                    ->update([
                        'pack_start_date' => $pack->pack_start_date ?? CarbonImmutable::parse($pack->start_at)->toDateString(),
                        'pack_end_date' => $pack->pack_end_date ?? CarbonImmutable::parse($pack->end_at)->toDateString(),
                    ]);
            });

        Schema::table('training_packs', function (Blueprint $table): void {
            $table->date('pack_start_date')->nullable(false)->change();
            $table->date('pack_end_date')->nullable(false)->change();
        });
    }
};
