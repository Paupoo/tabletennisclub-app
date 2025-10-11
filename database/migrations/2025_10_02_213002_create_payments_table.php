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
        Schema::dropIfExists('payments');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('transaction_id')->unique()->nullable();
            $table->unsignedSmallInteger('amount_due');
            $table->unsignedSmallInteger('amount_paid');
            $table->enum(column: 'status', allowed: ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->morphs('payable');
            $table->timestamps();
        });
    }
};
