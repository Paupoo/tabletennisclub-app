<?php

declare(strict_types=1);

use App\Domains\Meetings\Models\MeetingUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meeting_user', function (Blueprint $table): void {
            $table->dropColumn(['meal_reserved', 'meal_responded_at']);
        });
    }

    public function up(): void
    {
        Schema::table('meeting_user', function (Blueprint $table): void {
            // null = not answered, true = meal reserved, false = meal declined
            $table->boolean('meal_reserved')->nullable()->after('response_at');
            $table->dateTime('meal_responded_at')->nullable()->after('meal_reserved');
        });

        // Backfill: any registration that already carries a payment was reserved
        // under the previous auto-create flow — keep the admin view accurate.
        $morphClass = (new MeetingUser)->getMorphClass();

        DB::table('meeting_user')
            ->whereIn('id', function ($query) use ($morphClass): void {
                $query->select('payable_id')
                    ->from('payments')
                    ->where('payable_type', $morphClass);
            })
            ->update(['meal_reserved' => true]);
    }
};
