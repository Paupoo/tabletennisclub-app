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
        Schema::dropIfExists('transactions');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('date');
            $table->text('description');
            $table->string('amount');
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_bank_account')->nullable();
            $table->string('structured_reference')->nullable();
            $table->string('free_reference')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
