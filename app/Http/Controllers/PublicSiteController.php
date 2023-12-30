<?php

namespace App\Http\Controllers;

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
        ]);
    }
}
