<?php

declare(strict_types=1);

use App\Models\ClubEvents\Interclub\Season;
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
        Schema::dropIfExists('training_packs');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('training_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignIdFor(Season::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price')->default(0);
            $table->timestamps();
        });
    }
};
