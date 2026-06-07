<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Interclub;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ResultsController extends Controller
{
    public function index(): View
    {
        return view('public.results');
    }
}
