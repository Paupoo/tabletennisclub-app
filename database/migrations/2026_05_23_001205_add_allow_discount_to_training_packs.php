<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->dropColumn('allow_discount');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->boolean('allow_discount')->default(true)->after('price');
        });
    }
};
