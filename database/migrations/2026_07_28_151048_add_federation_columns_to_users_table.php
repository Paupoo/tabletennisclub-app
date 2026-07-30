<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facts the federation owns about a member, sitting next to `licence` and
 * `ranking` which already live here.
 *
 * `federation_licence_type` keeps the federation's own codes — JO (joueur,
 * competition) and LR (loisir, recreational) — on purpose. The club already
 * says "competitive" through `subscriptions.is_competitive`, and "active
 * member" through an affiliation being confirmed or paid. Translating JO/LR
 * into that vocabulary would hand the club a fourth concept wearing a third
 * name; the raw codes make the confusion impossible.
 *
 * `federation_synced_at` dates the file the values came from. Without it the
 * mismatch warning raised when a member's own choice contradicts the federation
 * cannot say *when* — and a ten-month-old discrepancy is not discussed like a
 * three-day-old one.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['federation_licence_type', 'federation_synced_at']);
        });
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('federation_licence_type', ['JO', 'LR'])->nullable()->after('licence');
            $table->timestamp('federation_synced_at')->nullable()->after('federation_licence_type');
        });
    }
};
