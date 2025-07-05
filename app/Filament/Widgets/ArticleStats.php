<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ArticleStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Articles', Article::count())
                ->icon(Heroicon::UserGroup)
                ->description(__('Total articles')),
        ];
    }
}