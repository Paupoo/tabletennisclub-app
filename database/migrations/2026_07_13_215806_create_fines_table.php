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
        Schema::dropIfExists('fines');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('amount'); // stored in cents, mirrors payments.amount_due
            $table->string('reason');
            $table->string('federation_reference')->nullable();
            $table->text('description')->nullable();
            $table->text('pedagogical_message');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
