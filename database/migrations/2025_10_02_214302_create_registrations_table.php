<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubPosts\EventPost;
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
        Schema::dropIfExists('registrations');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(EventPost::class);
            $table->foreignIdFor(User::class);
            $table->unsignedSmallInteger('amount_due')->default(0);
            $table->unsignedSmallInteger('amount_paid')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
};
