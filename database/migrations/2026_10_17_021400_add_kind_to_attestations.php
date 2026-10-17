<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second kind of certificate: a training camp, alongside the affiliation.
 *
 * An insurer publishes one form for a club affiliation and another for a camp,
 * so a template is now unique per insurer *and* kind. Every row already here
 * is an affiliation, which is what the default says.
 *
 * A camp attestation certifies one enrolment line, not the whole affiliation:
 * the line is what carries the camp's own payments. The day counts are copied
 * onto the row like every other fact the document states — the club put its
 * seal to « 5 jours », not to a query over sessions that may be cancelled
 * after the fact.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('mutual_attestations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_training_pack_id');
            $table->dropColumn(['kind', 'full_days', 'half_days']);
        });

        Schema::table('attestation_templates', function (Blueprint $table): void {
            $table->dropUnique(['mutuality', 'kind']);
            $table->dropColumn('kind');
            $table->unique('mutuality');
        });
    }

    public function up(): void
    {
        Schema::table('attestation_templates', function (Blueprint $table): void {
            $table->dropUnique(['mutuality']);
            $table->string('kind')->default('affiliation')->after('mutuality');
            $table->unique(['mutuality', 'kind']);
        });

        Schema::table('mutual_attestations', function (Blueprint $table): void {
            $table->string('kind')->default('affiliation')->after('season_id');
            $table->foreignId('subscription_training_pack_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('subscription_training_pack')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('full_days')->nullable()->after('period_to');
            $table->unsignedSmallInteger('half_days')->nullable()->after('full_days');
        });
    }
};
