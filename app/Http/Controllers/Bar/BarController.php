<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;

class BarController extends Controller
{
    public function index()
    {
        return view('bar.index');
    }
}
