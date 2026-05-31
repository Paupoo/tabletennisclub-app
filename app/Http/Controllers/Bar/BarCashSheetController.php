<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\Bar\BarOrder;
use App\Models\Bar\BarOrderItem;
use App\Services\Bar\CashSheetService;
use Illuminate\Http\Request;

class BarCashSheetController extends Controller
{
    private CashSheetService $cashSheetService;

    public function __construct(CashSheetService $cashSheetService)
    {
        $this->middleware('auth');
        $this->cashSheetService = $cashSheetService;
    }

    public function index(Request $request)
    {
        // 1. Get selected date (default = today)
        $validated = $request->validate([
            'date' => 'nullable|date'
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        
        [$summary, $rows, $csv] = $this->cashSheetService->build($date);

return view('bar.cashSheet.index', [
            'date' => $date,
            'summary' => $summary,
            'rows' => $rows,
            'defaultTo' => $this->cashSheetService->getDefaultEmail(),
        ]);

    }
}