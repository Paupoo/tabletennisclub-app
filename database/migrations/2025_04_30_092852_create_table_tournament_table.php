<?php

declare(strict_types=1);

use App\Models\Table;
use App\Models\Tournament;
use App\Models\TournamentMatch;
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
        Schema::dropIfExists('table_tournament');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_tournament', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Tournament::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Table::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(TournamentMatch::class)->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_table_free')->default(true);
            $table->dateTime('match_started_at')->nullable()->default(null);
            $table->dateTime('match_ended_at')->nullable()->default(null);
            $table->timestamps();
        });
    }
};
