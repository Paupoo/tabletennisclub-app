<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubPosts;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PublicEventPostController extends Controller
{
    public function index(): View
    {
        return view('public.events');
    }
}
