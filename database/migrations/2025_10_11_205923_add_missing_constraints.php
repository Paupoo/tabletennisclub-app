<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // training_user: add FK only if missing (avoid duplicate-key errors)
        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'training_user', 'user_id']
        );
        if (empty($exists)) {
            Schema::table('training_user', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }

        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'training_user', 'training_id']
        );
        if (empty($exists)) {
            Schema::table('training_user', function (Blueprint $table) {
                $table->foreign('training_id')
                      ->references('id')
                      ->on('trainings')
                      ->onDelete('cascade');
            });
        }
        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'interclub_user', 'user_id']
        );
        if (empty($exists)) {
            Schema::table('interclub_user', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }

        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'interclub_user', 'interclub_id']
        );
        if (empty($exists)) {
            Schema::table('interclub_user', function (Blueprint $table) {
                $table->foreign('interclub_id')
                      ->references('id')
                      ->on('interclubs')
                      ->onDelete('cascade');
            });
        }
        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'subscriptions', 'user_id']
        );
        if (empty($exists)) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }

        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'subscriptions', 'season_id']
        );
        if (empty($exists)) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreign('season_id')
                      ->references('id')
                      ->on('seasons')
                      ->onDelete('cascade');
            });
        }
        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'registrations', 'user_id']
        );
        if (empty($exists)) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }

        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [env('DB_DATABASE'), 'registrations', 'event_id']
        );
        if (empty($exists)) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->foreign('event_id')
                      ->references('id')
                      ->on('events')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['training_id']);
        });
        Schema::table('interclub_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['interclub_id']);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['season_id']);
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['event_id']);
        });
    }
};
