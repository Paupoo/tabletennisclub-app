<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;

class ForceIndex
{
    
     /**
     * Calculate force index for every registered players and store into the DB.
     */
    public function setOrUpdate(): void
    {
        $competitors = $this->countCompetitorsByRanking();
        $this->storeForceIndexPerRanking($competitors);
    }

    /**
     * Delete force index for all members in the db.
     */
    public static function delete()
    {
        User::where('force_index', '!=', null)->update(['force_index' => null]);
        return redirect()->route('members.index');
    }

    private function countCompetitorsByRanking(): Collection
    {
        $members = User::select('ranking', DB::raw('count(1) as total'))
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

        return $members->push($totalE6Nc);
    }

    private function storeForceIndexPerRanking($members): void
    {
        $i = 0;
        foreach ($members as $member) {
            if ($member->ranking !== 'E6-NC') {
                User::where('ranking', $member->ranking)
                ->where('is_competitor', true)
                ->update(['force_index' => $member->total + $i]);
                $i += $member->total;
            } elseif ($member->ranking === 'E6-NC') {
                User::whereIn('ranking', ['E6', 'NC'])
                    ->where('is_competitor', true)
                    ->update(['force_index' => $member->total + $i]);
            }
        }
    }
}