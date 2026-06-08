<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Shared\Models\AppSetting;
use Illuminate\Database\Seeder;

class InterclubSettingsSeeder extends Seeder
{
    /**
     * Seed default interclub schedule settings if not already present.
     */
    public function run(): void
    {
        /** @var array<string, string> $defaults */
        $defaults = [
            'interclub_schedule_enabled' => '1',
            'interclub_schedule_day' => 'Vendredi',
            'interclub_schedule_time_start' => '19:00',
            'interclub_schedule_time_end' => '23:30',
            'interclub_schedule_location' => 'Demeester (0 et -1)',
            'interclub_schedule_description' => 'Matches de compétition à domicile. Venez nous supporter !',
        ];

        foreach ($defaults as $key => $value) {
            if (! AppSetting::where('key', $key)->exists()) {
                AppSetting::set($key, $value);
            }
        }
    }
}
