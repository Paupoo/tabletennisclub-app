<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(__('Active Users'), User::where('is_active', true)->count())
                ->icon(Heroicon::User)
                ->description(__('Users that are currently active')),
            Stat::make(__('Competitors'), User::where('is_competitor', true)->count())
                ->icon(Heroicon::ChartBar)
                ->description(__('Users registered as competitors')),
            Stat::make('Teams', Team::count())
                ->icon(Heroicon::UserGroup)
                ->description(__('Total number of teams')),
            Stat::make(__('Interclubs winning rate'), '42%')
                ->icon(Heroicon::Trophy)
                ->description(__('Winning rate of interclub matches')),
        ];
    }
}
