<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::rename('interclub_results', 'match_results');
    }

    public function up(): void
    {
        Schema::rename('match_results', 'interclub_results');
    }
};
