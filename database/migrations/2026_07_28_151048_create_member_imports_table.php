<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per run of the federation affiliate listing import, mirroring the
 * `bank_imports` table the treasury already uses.
 *
 * `failed_rows` holds a line number and a reason, and nothing else. The source
 * file carries names, birthdates, postal addresses and phone numbers of minors;
 * none of it is retained here, and no snapshot of the file is kept anywhere —
 * the upload is deleted as soon as the import is committed.
 *
 * `users.member_import_id` records provenance, not state: it answers "where did
 * this member come from", it survives forever, and it drives no status display.
 * Whether a member has been contacted is read from `last_invited_at`, and
 * whether they are an active member from their season affiliation.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['member_import_id']);
            $table->dropColumn('member_import_id');
        });

        Schema::dropIfExists('member_imports');
    }

    public function up(): void
    {
        Schema::create('member_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('failed_rows')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('member_import_id')
                ->nullable()
                ->after('last_invited_at')
                ->constrained('member_imports')
                ->nullOnDelete();
        });
    }
};
