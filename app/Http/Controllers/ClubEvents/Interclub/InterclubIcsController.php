<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Interclub;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Http\Controllers\Controller;
use App\Services\IcsGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * One fixture as a downloadable calendar file.
 *
 * Session-authenticated and behind the same policy as the page it sits on, not
 * signed like the personal feed: this one is fetched by the member's browser on
 * a click, never polled by a calendar provider, so there is no reason to make
 * the URL a secret that outlives the click.
 */
class InterclubIcsController extends Controller
{
    public function __invoke(Interclub $interclub, IcsGenerator $ics): Response
    {
        Gate::authorize('viewMatchPage', $interclub);

        abort_if($interclub->is_bye, 404);

        $filename = 'interclub-' . $interclub->start_date_time->format('Y-m-d') . '.ics';

        return response($ics->forInterclub($interclub, Auth::user()), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
