<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        $map = [
            'JOIN_US' => 'join',
            'TRIAL' => 'try',
            'INFO_INTERCLUBS' => 'info_interclubs',
            'BECOME_SUPPORTER' => 'become_supporter',
            'PARTNERSHIP' => 'partnership',
        ];

        foreach ($map as $old => $new) {
            DB::table('contacts')->where('interest', $old)->update(['interest' => $new]);
        }
    }

    public function up(): void
    {
        $map = [
            'join' => 'JOIN_US',
            'try' => 'TRIAL',
            'info_interclubs' => 'INFO_INTERCLUBS',
            'become_supporter' => 'BECOME_SUPPORTER',
            'partnership' => 'PARTNERSHIP',
        ];

        foreach ($map as $old => $new) {
            DB::table('contacts')->where('interest', $old)->update(['interest' => $new]);
        }
    }
};
