<?php

use App\Models\Competition;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpParser\Node\Expr\Match_;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competition_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Competition::class);
            $table->foreignIdFor(User::class);
            $table->boolean('is_available');
            $table->boolean('is_selected');
            $table->boolean('has_played');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_user');
    }
};
