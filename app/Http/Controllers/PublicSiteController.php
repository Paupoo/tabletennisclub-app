<?php

namespace App\Http\Controllers;

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use Illuminate\Http\Request;
use App\Models\Training;

class PublicSiteController extends Controller
{
    //
    public function homePage()
    {
        $now = now();
        return view('/public/welcome', [
            'trainings' => Training::where('start', '>=', $now)->orderBy('start', 'asc')->take(5)->get(),
            'training_levels' => collect(TrainingLevel::cases()),
            'training_types' => collect(TrainingType::cases()),
        ]);
    }
}
