<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('subscription_training_pack', function (Blueprint $table): void {
            $table->dropColumn(['status', 'waitlist_position', 'confirmation_deadline']);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_training_pack', function (Blueprint $table): void {
            // enrolled | waiting | offered
            $table->string('status')->default('enrolled')->after('discount');
            $table->integer('waitlist_position')->nullable()->after('status');
            $table->timestamp('confirmation_deadline')->nullable()->after('waitlist_position');
        });
    }
};
