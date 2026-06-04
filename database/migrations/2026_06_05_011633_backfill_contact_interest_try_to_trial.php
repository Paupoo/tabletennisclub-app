<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        DB::table('contacts')
            ->where('interest', 'TRIAL')
            ->update(['interest' => 'try']);
    }

    public function up(): void
    {
        DB::table('contacts')
            ->where('interest', 'try')
            ->update(['interest' => 'TRIAL']);
    }
};
