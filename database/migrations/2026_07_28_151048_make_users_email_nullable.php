<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An email address identifies a login; it does not designate a person to contact.
 *
 * The application conflated the two, which made whole categories of member
 * impossible to record: siblings affiliated under one parent's address, and
 * children too young to own a mailbox at all. A null email now means "this
 * account exists and is not connectable yet" — the member is reached through
 * their guardian, via {@see User::contactEmail()}.
 *
 * The unique index is deliberately kept: MySQL allows several NULLs under it,
 * so it still guarantees one account per address wherever an address exists.
 */
return new class extends Migration
{
    /**
     * Irreversible once a single managed account exists: bringing the column
     * back to NOT NULL would have to invent an address for every member who has
     * none. Rolling back is only safe while no row holds a null email —
     * otherwise, forward-fix.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }
};
