<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting documents: the invoice, the ticket, the subsidy letter that
 * justifies money the website never saw.
 *
 * A document is linked, without any amount, to the bank lines and the cash
 * register movements that paid it — several documents may share a debit and a
 * document may be paid in several goes. Its state (to settle, settled) is read
 * off those links and never stored.
 *
 * Exactly one of the two category columns is filled: the direction of the
 * money follows from which one.
 *
 * The cash register entries gain the bank line a till deposit (or a float
 * withdrawn from the bank) was linked to: money moving between the till and
 * the bank is internal, neither income nor expense.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('cash_register_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transaction_id');
        });

        Schema::dropIfExists('cash_register_entry_supporting_document');
        Schema::dropIfExists('supporting_document_transaction');
        Schema::dropIfExists('supporting_document_files');
        Schema::dropIfExists('supporting_documents');
    }

    public function up(): void
    {
        Schema::create('supporting_documents', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->string('expense_category', 32)->nullable()->index();
            $table->string('income_category', 32)->nullable()->index();
            $table->string('counterparty');
            $table->string('label');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supporting_document_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supporting_document_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->char('sha256', 64)->index();
            $table->timestamps();
        });

        Schema::create('supporting_document_transaction', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supporting_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supporting_document_id', 'transaction_id'], 'supporting_document_transaction_unique');
        });

        Schema::create('cash_register_entry_supporting_document', function (Blueprint $table): void {
            $table->id();
            // Named by hand: the generated names pass MySQL's 64 characters.
            $table->foreignId('supporting_document_id')->constrained(indexName: 'cresd_supporting_document_id_foreign')->cascadeOnDelete();
            $table->foreignId('cash_register_entry_id')->constrained(indexName: 'cresd_cash_register_entry_id_foreign')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supporting_document_id', 'cash_register_entry_id'], 'cash_register_entry_supporting_document_unique');
        });

        Schema::table('cash_register_entries', function (Blueprint $table): void {
            $table->foreignId('transaction_id')->nullable()->unique()->after('payable_id')->constrained()->nullOnDelete();
        });
    }
};
