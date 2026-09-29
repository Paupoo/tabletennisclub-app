<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fine is no longer a debt to the club: the member pays the provincial
 * committee directly. It now carries what that transfer needs — the event, its
 * date, the deadline — and no longer a payment of the club's.
 */
return new class extends Migration
{
    /**
     * Reverse the migrations.
     *
     * The old reasons and the cancelled payments are not brought back: nothing
     * tells which of them a row had.
     */
    public function down(): void
    {
        Schema::table('fines', function (Blueprint $table): void {
            $table->string('federation_reference')->nullable()->after('reason');
        });

        Schema::table('fines', function (Blueprint $table): void {
            $table->dropColumn(['event_date', 'event_label', 'payment_deadline', 'provincial_code']);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fines', function (Blueprint $table): void {
            // Nullable for the fines issued before: they held none of this.
            $table->date('event_date')->nullable()->after('reason');
            $table->string('event_label')->nullable()->after('event_date');
            $table->date('payment_deadline')->nullable()->after('event_label');
            $table->unsignedSmallInteger('provincial_code')->nullable()->after('reason');
        });

        // The committee never handed out a reference; the one or two typed in
        // anyway are kept in the internal note rather than dropped.
        DB::table('fines')->whereNotNull('federation_reference')->orderBy('id')->each(function (object $fine): void {
            DB::table('fines')->where('id', $fine->id)->update([
                'description' => trim(($fine->description ?? '') . "\n" . 'Réf. ' . $fine->federation_reference),
            ]);
        });

        Schema::table('fines', function (Blueprint $table): void {
            $table->dropColumn('federation_reference');
        });

        /*
         * The old reasons (forfeit, late, misconduct, unjustified absence) have
         * no counterpart in the provincial list. Production held a single fine
         * when this ran, for a refused refereeing duty (checked on 2026-09-29),
         * so that is what every old row becomes.
         */
        DB::table('fines')
            ->whereIn('reason', ['forfeit', 'late', 'misconduct', 'unjustified_absence', 'other'])
            ->update(['reason' => 'refereeing', 'provincial_code' => 67]);

        // The club collects nothing any more: a claim still open would stay
        // open forever, since no transfer to the club will ever settle it.
        DB::table('payments')
            ->where('payable_type', Fine::class)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);
    }
};
