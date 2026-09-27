<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_report_exports');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('format'); // pdf | zip
            // The reports as the screen showed them when asked: a report
            // decided in the meantime must not slip in or out of the file.
            $table->json('report_ids');
            $table->string('status')->default('pending'); // pending | ready | failed | expired
            $table->string('path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
};
