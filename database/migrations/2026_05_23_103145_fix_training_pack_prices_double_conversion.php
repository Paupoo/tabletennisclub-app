<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The training pack wizard was dividing the accessor result (already euros)
// by 100 on load, then multiplying by 100 on save, in addition to the
// Eloquent setter already doing *100. This caused a net *100 factor on every
// save, storing e.g. 900000 instead of the correct 9000 cents for a 90€ pack.
return new class extends Migration
{
    public function down(): void
    {
        DB::table('training_packs')->update([
            'price' => DB::raw('price * 100'),
        ]);
    }

    public function up(): void
    {
        DB::table('training_packs')->update([
            'price' => DB::raw('ROUND(price / 100)'),
        ]);
    }
};
