<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\Widgets\LatestUsers;
use App\Filament\Widgets\ArticleStats;
use App\Filament\Widgets\UserStats;
use App\Models\Article;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // // ...
    // protected function getHeaderWidgets(): array
    // {
    //     return [
    //         UserStats::class,
    //         ArticleStats::class,
    //         LatestUsers::class,
    //     ];
    // }
}
