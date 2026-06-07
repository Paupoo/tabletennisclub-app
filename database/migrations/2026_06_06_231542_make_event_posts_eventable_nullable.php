<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('event_posts', function (Blueprint $table): void {
            $table->string('eventable_type')->nullable(false)->change();
            $table->unsignedBigInteger('eventable_id')->nullable(false)->change();
        });
    }

    public function up(): void
    {
        Schema::table('event_posts', function (Blueprint $table): void {
            $table->string('eventable_type')->nullable()->change();
            $table->unsignedBigInteger('eventable_id')->nullable()->change();
        });
    }
};
