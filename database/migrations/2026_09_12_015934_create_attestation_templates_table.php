<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The form each insurer publishes, as the club currently holds it.
 *
 * The file is an artefact, so it lives here; what the club knows *about* each
 * insurer — its ceiling, whether it accepts a club-written attestation — is
 * versioned in the Mutuality enum instead, where it can be read in review.
 *
 * `unresolved_fields` is the readiness signal: a revised form whose wording
 * moved leaves the labels it no longer carries here, the test screen shows
 * them, and that insurer drops out of the member's list rather than producing
 * a document with holes in it.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('attestation_templates');
    }

    public function up(): void
    {
        Schema::create('attestation_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('mutuality')->unique();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedTinyInteger('page_count')->default(1);
            $table->json('unresolved_fields');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
