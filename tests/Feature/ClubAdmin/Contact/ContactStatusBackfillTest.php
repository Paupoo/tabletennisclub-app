<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The suite already runs on the narrowed enum, so widen it back first: that is
 * exactly the four-value shape this migration meets in production.
 */
function withLegacyPendingContactStatus(callable $seed): Migration
{
    $migration = require base_path('database/migrations/2026_07_25_134622_backfill_contact_pending_status_to_new.php');
    $migration->down();

    $seed();

    return $migration;
}

describe('pending contact status backfill', function (): void {
    it('moves every pending contact back to new and leaves the others alone', function (): void {
        $ids = [];

        $migration = withLegacyPendingContactStatus(function () use (&$ids): void {
            $ids['pending'] = Contact::factory()->create(['status' => 'pending'])->id;
            $ids['otherPending'] = Contact::factory()->create(['status' => 'pending'])->id;
            $ids['processed'] = Contact::factory()->create(['status' => 'processed'])->id;
            $ids['rejected'] = Contact::factory()->create(['status' => 'rejected'])->id;
            $ids['new'] = Contact::factory()->create(['status' => 'new'])->id;
        });

        $migration->up();

        expect(DB::table('contacts')->find($ids['pending'])->status)->toBe('new')
            ->and(DB::table('contacts')->find($ids['otherPending'])->status)->toBe('new')
            ->and(DB::table('contacts')->find($ids['processed'])->status)->toBe('processed')
            ->and(DB::table('contacts')->find($ids['rejected'])->status)->toBe('rejected')
            ->and(DB::table('contacts')->find($ids['new'])->status)->toBe('new');
    });

    it('remaps the pending apply_status carried by email templates', function (): void {
        $ids = [];

        $migration = withLegacyPendingContactStatus(function () use (&$ids): void {
            $ids['pending'] = EmailTemplate::factory()->appliesStatus('pending')->create()->id;
            $ids['rejected'] = EmailTemplate::factory()->appliesStatus('rejected')->create()->id;
            $ids['none'] = EmailTemplate::factory()->create(['apply_status' => null])->id;
        });

        $migration->up();

        expect(EmailTemplate::find($ids['pending'])->apply_status)->toBe('new')
            ->and(EmailTemplate::find($ids['rejected'])->apply_status)->toBe('rejected')
            ->and(EmailTemplate::find($ids['none'])->apply_status)->toBeNull();
    });

    it('narrows the status column so pending can no longer be stored', function (): void {
        $migration = withLegacyPendingContactStatus(fn () => null);
        $migration->up();

        expect(fn () => Contact::factory()->create(['status' => 'pending']))
            ->toThrow(QueryException::class);
    });
})->group('contact');
