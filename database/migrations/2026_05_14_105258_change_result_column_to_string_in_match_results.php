<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\InterclubResultEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->enum('result', array_column(InterclubResultEnum::cases(), 'value'))->nullable()->change();
        });
    }

    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->string('result')->nullable()->change();
        });
    }
};
