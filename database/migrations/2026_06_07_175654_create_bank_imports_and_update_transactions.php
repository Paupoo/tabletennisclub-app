<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_import_id']);
            $table->dropUnique(['import_fingerprint']);
            $table->dropColumn(['import_fingerprint', 'bank_import_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('date')->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('amount_str')->nullable()->after('description');
        });

        DB::update('UPDATE transactions SET amount_str = CAST(CAST(amount AS REAL) / 100 AS TEXT)');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount_str', 'amount');
        });

        Schema::dropIfExists('bank_imports');
    }

    public function up(): void
    {
        Schema::create('bank_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('failed_rows')->nullable();
            $table->timestamps();
        });

        // Add temp integer column for amount in cents
        Schema::table('transactions', function (Blueprint $table) {
            $table->bigInteger('amount_cents')->default(0)->after('description');
        });

        // Convert existing string amounts to integer cents
        DB::update('UPDATE transactions SET amount_cents = CAST(ROUND(CAST(amount AS REAL) * 100) AS INTEGER)');

        // Drop old string amount column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        // Rename temp column to amount
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('amount_cents', 'amount');
        });

        // Change date from string to DATE type
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('date')->change();
        });

        // Add new columns
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('import_fingerprint', 64)->nullable()->unique()->after('free_reference');
            $table->foreignId('bank_import_id')->nullable()->after('import_fingerprint')->constrained('bank_imports')->nullOnDelete();
        });
    }
};
