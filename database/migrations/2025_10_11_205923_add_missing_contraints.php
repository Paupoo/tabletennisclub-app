<?php

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
        Schema::table('training_user', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('training_id')
                  ->references('id')
                  ->on('trainings')
                  ->onDelete('cascade');
        });
        Schema::table('interclub_user', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('interclub_id')
                  ->references('id')
                  ->on('interclubs')
                  ->onDelete('cascade');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('season_id')
                  ->references('id')
                  ->on('seasons')
                  ->onDelete('cascade');
        });
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('event_id')
                  ->references('id')
                  ->on('events')
                  ->onDelete('cascade');
        });
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
