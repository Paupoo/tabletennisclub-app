<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('training_user', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function up(): void
    {
        Schema::table('training_user', function (Blueprint $table) {
            $table->string('status')->default('enrolled');
        });
    }
};
