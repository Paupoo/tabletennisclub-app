<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;

class ForceList
{
    protected $users;

     /**
     * Calculate force index for every registered players and store into the DB.
     */
    public function setOrUpdateAll(): void
    {
        $this->delete()->countCompetitorsByRanking()->assignForceIndexPerRanking($this->users);
    }

    /**
     * Delete all force indexes
     */
    public function delete(): self
    {
        User::where('force_list', '!=', null)->update(['force_list' => null]);

        return $this;
    }

    /**
     * Stores a collection, counting all the competitors grouped by their ranking and merging E6-NC
     *
     * @return self
     */
    private function countCompetitorsByRanking(): self
    {
        $users = User::select('ranking', DB::raw('count(1) as total'))
            ->whereNotIn('ranking', ['NA', 'NC', 'E6'])
            ->where ('is_competitor', true)
            ->groupby('ranking')
            ->orderBy('ranking', 'asc')
            ->get();

        $totalE6Nc = new \stdClass;
        $totalE6Nc->ranking = 'E6-NC';
        $totalE6Nc->total =  User::whereIn('ranking', ['E6','NC'])
                                                ->where('is_competitor', true)
                                                ->count();
        $this->users = $users->push($totalE6Nc);

        return $this;
    }

    /**
     * Assign force index for each competitor
     *
     * @param Collection $users
     * @return self
     */
    private function assignForceIndexPerRanking(Collection $users): self
    {
        $i = 0;
        foreach ($users as $user) {
            if ($user->ranking !== 'E6-NC') {
                User::where('ranking', $user->ranking)
                ->where('is_competitor', true)
                ->update(['force_list' => $user->total + $i]);
                $i += $user->total;
            } elseif ($user->ranking === 'E6-NC') {
                User::whereIn('ranking', ['E6', 'NC'])
                    ->where('is_competitor', true)
                    ->update(['force_list' => $user->total + $i]);
            }
        }

        return $this;
    }
}