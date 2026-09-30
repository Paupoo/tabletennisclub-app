<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Support\PaymentCoversBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('covers');
        });
    }

    /**
     * What a payment bills: the affiliation, training packs, or both.
     *
     * A subscription carries several payments — the first one, then a
     * complement for every pack added, formula or pack changed — and every one
     * of them was labelled "Affiliation 2026-2027". A parent adding a pack to a
     * child already affiliated could not tell what she was asked to pay.
     *
     * The payments already made are rebuilt from the dates, see
     * payments:backfill-covers (run it with --dry-run first to read the report).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->json('covers')->nullable()->after('payable_id');
        });

        (new PaymentCoversBackfill)->run();
    }
};
