<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }

    /**
     * A message the committee wrote to the club from the application. Kept for
     * good: it is the club's record of what it said, and a template for the
     * same message next season.
     */
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('reply_to')->nullable();
            // The filters the audience was computed from, not the list itself.
            $table->json('criteria');
            $table->unsignedInteger('member_count');
            $table->unsignedInteger('recipient_count');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
};
