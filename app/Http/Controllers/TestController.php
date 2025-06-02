<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InterclubType;
use App\Models\Interclub;
use App\Services\InterclubService;
use Illuminate\View\View;

class TestController extends Controller
{
    //

    public function __construct(private InterclubService $interclubService) {}

    public function test(): View
    {

        $test = new Interclub;
        $test->setTotalPlayersPerteam(InterclubType::MEN->name);
        $test->setWeekNumber();

        return View('/test', [
            'test' => $test,
        ]);
    }
}
