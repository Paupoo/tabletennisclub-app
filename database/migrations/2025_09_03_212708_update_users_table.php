<?php

declare(strict_types=1);

use App\Enums\Sex;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('password', 255)->nullable()->change();
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password', 255)->nullable()->change();
            $table->enum('sex', array_column(Sex::cases(), 'name'))->default(Sex::MEN->name)->change();
        });
    }
};
