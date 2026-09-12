<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one row that says how an attestation is signed and stamped.
 *
 * A single row rather than columns on `clubs`: that table also holds the 56
 * opposing clubs of the interclub calendar, which would each carry five
 * eternally empty columns. And rather than AppSetting keys, because a file
 * path and a foreign key deserve a type and an integrity constraint.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('attestation_settings');
    }

    public function up(): void
    {
        Schema::create('attestation_settings', function (Blueprint $table): void {
            $table->id();

            // The person whose name and signature the documents carry. Nullable
            // because the row exists before anyone has been designated, and the
            // readiness gate is what keeps the feature shut until then.
            $table->foreignId('signatory_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('seal_path')->nullable();
            $table->unsignedSmallInteger('seal_width_mm')->default(30);
            $table->string('signature_path')->nullable();
            $table->unsignedSmallInteger('signature_width_mm')->default(42);

            // Partenamut asks which federation the club belongs to; every form
            // asks which sport is practised. Both are stable, both are editable
            // rather than hard-coded, because neither is ours to freeze.
            $table->string('federation_name')->default('AFTT');
            $table->string('discipline')->default('Tennis de table');

            $table->timestamps();
        });
    }
};
