<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('last_invited_at');
        });
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('updated_at');
            $table->timestamp('last_invited_at')->nullable()->after('updated_by');
        });
    }
};
