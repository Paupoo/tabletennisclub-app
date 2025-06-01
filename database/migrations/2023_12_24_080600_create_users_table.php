<?php

declare(strict_types=1);

use App\Enums\Ranking;
use App\Enums\Sex;
use App\Models\Club;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_comittee_member')->default(false);
            $table->boolean('is_competitor')->default(false);
            $table->boolean('has_debt')->default(false);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('sex', array_column(Sex::cases(), 'name'));
            $table->string('phone_number', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('street', 255)->nullable();
            $table->string('city_code', 10)->nullable();
            $table->string('city_name', 100)->nullable();
            $table->enum('ranking', array_column(Ranking::cases(), 'name'))->default(Ranking::NA->name);
            $table->string('licence', 6)->unique()->nullable();
            $table->unsignedTinyInteger('force_list')->nullable();
            $table->foreignIdFor(Club::class)->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
