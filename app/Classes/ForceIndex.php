<?php

namespace App\Classes;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ForceIndex
{
    /**
     * Calculate force index for every registered players and store into the DB.
     */
    public static function setForceIndex() 
    {
        
        // Get aggregated counts by ranking (i.e. [B6]=>1, [D4]=>5, ...) but exclude E6 and NC players
        $members = DB::table('users')
            ->select('ranking', DB::raw('count(1) as total'))
            ->whereNot('ranking', 'NA')
            ->whereNot('ranking', null)
            ->groupby('ranking')
            ->orderBy('ranking', 'asc')
            ->get();

        // Get count of total E6 & NC players
        $totalE6_and_NC_users = User::whereIn('ranking', ['E6','NC'])
            ->count();

        // read the whole table, calculate force index for each ranking and update members in the db except for E6/NC.
        $i = 0;
        foreach ($members as $member) {
            if ($member->ranking == 'E6' || $member->ranking == 'NC') {
                null;
            } elseif ($member->ranking != 'E6' || $member->ranking != 'NC') {
                User::where('ranking', '=', $member->ranking)->update(['force_index' => ($member->total + $i)]);
                $i = $member->total + $i;
            }
        }

        // For E6 and NC players, simply mass update their count + last value of $i
        User::whereIn('ranking', ['E6','NC'])->update(['force_index' => $totalE6_and_NC_users + $i]);
        
        unset($i);

        return redirect()->route('members.index');
    }

    /**
     * Delete force index for all members in the db.
     */
    public static function deleteForceIndex()
    {
        User::where('force_index', '!=', null)->update(['force_index' => null]);
        return redirect()->route('members.index');
    }
}