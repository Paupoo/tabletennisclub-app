<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interclub_results', function (Blueprint $table): void {
            $table->foreignId('interclub_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('interclubs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('interclub_results', function (Blueprint $table): void {
            $table->dropForeign(['interclub_id']);
            $table->dropColumn('interclub_id');
        });
    }
};
