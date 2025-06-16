<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\Article;
use App\Models\Tournament;
use App\Models\Training;

class PublicSiteController extends Controller
{
    //
    public function homePage()
    {
        $now = now();

        return view('/public/welcome', [
            'articles' => Article::latest()->take(5)->get(),
            'trainings' => Training::where('start', '>=', $now)->orderBy('start', 'asc')->take(5)->get(),
            'training_levels' => collect(TrainingLevel::cases()),
            'training_types' => collect(TrainingType::cases()),
            'tournaments' => Tournament::where('start_date', '>', today())->where('status', 'open')->with('rooms')->get(),
        ]);
    }
}
