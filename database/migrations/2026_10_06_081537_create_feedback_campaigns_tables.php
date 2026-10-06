<?php

declare(strict_types=1);

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
        Schema::table('feedback_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('feedback_campaign_response_id');
        });
        Schema::dropIfExists('feedback_campaign_participants');
        Schema::dropIfExists('feedback_campaign_responses');
        Schema::dropIfExists('feedback_campaigns');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->text('intro');
            $table->string('year_question', 255)->nullable();
            $table->date('opens_on');
            $table->date('closes_on');
            // Null while a draft: nothing is sent until someone schedules it.
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('summarised_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feedback_campaign_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_campaign_id')->constrained()->cascadeOnDelete();
            // Null when the member stayed anonymous: see AnswerCampaign.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('year_answer')->nullable();
            $table->timestamps();

            $table->unique(['feedback_campaign_id', 'user_id']);
        });

        /*
         * Who answered, and nothing else: no id, no time. Keyed on the pair, so
         * the storage order follows the member ids rather than the order of the
         * answers — nothing here can be lined up with an anonymous response.
         * Reminders skip whoever is listed.
         */
        Schema::create('feedback_campaign_participants', function (Blueprint $table): void {
            $table->foreignId('feedback_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['feedback_campaign_id', 'user_id']);
        });

        Schema::table('feedback_entries', function (Blueprint $table): void {
            // A comment left on one theme of a survey answer; null in the box.
            $table->foreignId('feedback_campaign_response_id')->nullable()->after('feedback_theme_id')
                ->constrained()->cascadeOnDelete();
        });
    }
};
