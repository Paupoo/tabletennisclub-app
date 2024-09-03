<?php

use App\Models\Interclub;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interclub_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Interclub::class);
            $table->foreignIdFor(User::class);
            $table->boolean('is_subscribed')->default(false);
            $table->boolean('is_selected')->default(false);
            $table->boolean('has_played')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interclub_user');
    }
};
