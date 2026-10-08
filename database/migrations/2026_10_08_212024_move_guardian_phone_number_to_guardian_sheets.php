<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `users.guardian_phone_number` held the number of whoever answers for a minor,
 * beside the guardian sheets that hold it too — and a sheet corrected by hand
 * left the old number shown elsewhere. The number moves onto the sheet when the
 * sheet has none; a sheet that has one keeps it, and the dropped value goes to
 * the log. A minor nobody is named for keeps theirs: it is the only way left to
 * reach their family until the office records a guardian.
 */
return new class extends Migration
{
    public function down(): void
    {
        // Data move only: the values written to the sheets are not told apart
        // from those typed there, and the dropped ones are in the log.
    }

    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('guardian_phone_number')
            ->where('guardian_phone_number', '!=', '')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('guardian_user')->whereColumn('guardian_user.user_id', 'users.id'))
            // By id, not by offset: each row leaves the filter once handled.
            ->lazyById()
            ->each(function (object $user): void {
                $sheets = DB::table('guardians')
                    ->join('guardian_user', 'guardian_user.guardian_id', '=', 'guardians.id')
                    ->where('guardian_user.user_id', $user->id)
                    ->whereNull('guardians.user_id')
                    ->select('guardians.id', 'guardians.phone')
                    ->get();

                $blank = $sheets->first(fn (object $sheet): bool => trim((string) $sheet->phone) === '');

                if ($blank !== null) {
                    DB::table('guardians')->where('id', $blank->id)->update(['phone' => $user->guardian_phone_number]);
                } else {
                    Log::info('guardian_phone_number dropped: the guardian sheets already hold a number.', [
                        'user_id' => $user->id,
                        'guardian_phone_number' => $user->guardian_phone_number,
                    ]);
                }

                DB::table('users')->where('id', $user->id)->update(['guardian_phone_number' => null]);
            });
    }
};
