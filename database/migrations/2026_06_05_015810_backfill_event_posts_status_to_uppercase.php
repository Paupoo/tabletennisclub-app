<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        $map = [
            'DRAFT' => 'draft',
            'PUBLISHED' => 'published',
            'ARCHIVED' => 'archived',
        ];

        foreach ($map as $old => $new) {
            DB::table('event_posts')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function up(): void
    {
        $map = [
            'draft' => 'DRAFT',
            'published' => 'PUBLISHED',
            'archived' => 'ARCHIVED',
        ];

        foreach ($map as $old => $new) {
            DB::table('event_posts')->where('status', $old)->update(['status' => $new]);
        }
    }
};
