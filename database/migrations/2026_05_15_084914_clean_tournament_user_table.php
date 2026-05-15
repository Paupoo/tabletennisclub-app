<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE tournament_user_old (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER,
                tournament_id INTEGER,
                pool_id     INTEGER,
                has_paid    TINYINT(1) NOT NULL DEFAULT 0,
                matches_won INTEGER NOT NULL DEFAULT 0,
                sets_won    INTEGER NOT NULL DEFAULT 0,
                points_won  INTEGER NOT NULL DEFAULT 0,
                registration_status VARCHAR NOT NULL DEFAULT \'registered\',
                waitlist_position   INTEGER,
                confirmation_deadline DATETIME,
                payment_deadline    DATETIME,
                payment_id  INTEGER,
                created_at  DATETIME,
                updated_at  DATETIME,
                UNIQUE (user_id, tournament_id, pool_id),
                FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE SET NULL,
                FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
                FOREIGN KEY (pool_id)       REFERENCES pools(id)       ON DELETE SET NULL,
                FOREIGN KEY (payment_id)    REFERENCES payments(id)    ON DELETE SET NULL
            )
        ');

        DB::statement('
            INSERT INTO tournament_user_old
                (id, user_id, tournament_id, has_paid, registration_status,
                 waitlist_position, confirmation_deadline, payment_deadline, payment_id,
                 created_at, updated_at)
            SELECT id, user_id, tournament_id, has_paid, registration_status,
                   waitlist_position, confirmation_deadline, payment_deadline, payment_id,
                   created_at, updated_at
            FROM tournament_user
        ');

        Schema::drop('tournament_user');

        DB::statement('ALTER TABLE tournament_user_old RENAME TO tournament_user');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE tournament_user_new (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER,
                tournament_id INTEGER,
                has_paid    TINYINT(1) NOT NULL DEFAULT 0,
                registration_status VARCHAR NOT NULL DEFAULT \'registered\',
                waitlist_position   INTEGER,
                confirmation_deadline DATETIME,
                payment_deadline    DATETIME,
                payment_id  INTEGER,
                created_at  DATETIME,
                updated_at  DATETIME,
                UNIQUE (user_id, tournament_id),
                FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE SET NULL,
                FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
                FOREIGN KEY (payment_id)    REFERENCES payments(id)    ON DELETE SET NULL
            )
        ');

        DB::statement('
            INSERT INTO tournament_user_new
                (id, user_id, tournament_id, has_paid, registration_status,
                 waitlist_position, confirmation_deadline, payment_deadline, payment_id,
                 created_at, updated_at)
            SELECT id, user_id, tournament_id, has_paid, registration_status,
                   waitlist_position, confirmation_deadline, payment_deadline, payment_id,
                   created_at, updated_at
            FROM tournament_user
        ');

        Schema::drop('tournament_user');

        DB::statement('ALTER TABLE tournament_user_new RENAME TO tournament_user');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
