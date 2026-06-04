<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refund_transaction_id']);
            $table->dropColumn('refund_transaction_id');
        });
    }

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('refund_transaction_id')
                ->nullable()
                ->unique()
                ->after('transaction_id')
                ->constrained('transactions')
                ->nullOnDelete();
        });
    }
};
