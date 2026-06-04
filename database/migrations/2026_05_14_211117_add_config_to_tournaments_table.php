<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'start_time',
                'duration_minutes',
                'pool_size',
                'nb_pools',
                'nb_qualifiers_per_pool',
                'sets_to_win',
                'logistics_buffer_minutes',
                'match_type',
            ]);
        });
    }

    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('start_date');
            $table->unsignedSmallInteger('duration_minutes')->default(180)->after('start_time');
            $table->unsignedTinyInteger('pool_size')->default(4)->after('duration_minutes');
            $table->unsignedTinyInteger('nb_pools')->default(4)->after('pool_size');
            $table->unsignedTinyInteger('nb_qualifiers_per_pool')->default(2)->after('nb_pools');
            $table->unsignedTinyInteger('sets_to_win')->default(3)->after('nb_qualifiers_per_pool');
            $table->unsignedTinyInteger('logistics_buffer_minutes')->default(3)->after('sets_to_win');
            $table->string('match_type')->default('single')->after('logistics_buffer_minutes');
        });
    }
};
