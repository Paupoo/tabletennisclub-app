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
        Schema::dropIfExists('expense_report_files');
        Schema::dropIfExists('expense_reports');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_reports', function (Blueprint $table): void {
            $table->id();
            // Restrict, not cascade: an accepted report is an accounting record
            // the club must keep, whoever asks for the member to be erased.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category');
            $table->string('description');
            $table->integer('amount'); // stored in cents, mirrors payments.amount_due
            $table->integer('accepted_amount')->nullable(); // cents; never above amount
            $table->date('spent_on');
            $table->string('refund_iban');
            $table->string('status')->index();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('resumed_from_id')->nullable()->constrained('expense_reports')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('files_purged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expense_report_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_report_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->char('sha256', 64)->index();
            $table->timestamps();
        });
    }
};
