<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        if (! Schema::hasColumn('event_posts', 'eventable_id')) {
            return;
        }

        Schema::table('event_posts', function (Blueprint $table): void {
            $table->dropMorphs('eventable');
        });
    }

    public function up(): void
    {
        if (Schema::hasColumn('event_posts', 'eventable_id')) {
            return;
        }

        Schema::table('event_posts', function (Blueprint $table): void {
            $table->nullableMorphs('eventable');
        });
    }
};
