<?php
declare(strict_types=1);

namespace App\Actions\Tournament;

use App\Actions\ToggleHasPaidAction;
use App\Models\Tournament;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ToggleHasPaidTournamentAction extends ToggleHasPaidAction
{
    /**
     * Toggle "has_paid" column from tournament_user database.
     * @param Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    public function toggleHasPaid(Model $model): bool
    {
        /**
         * @var Tournament $tournament
         */
        $tournament = $model;

        $pivot = $tournament->users()->where('user_id', $this->user->id)->first()?->pivot;

        if(!$pivot){
            throw new Exception(__('Relation not found'));
        }

        DB::transaction(fn () => $pivot->has_paid = ! $pivot->has_paid);

        return $pivot->save();
    }
}