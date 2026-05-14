<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        // Restore original CHECK constraint with the four original values.
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE match_results_old (
                id         integer primary key autoincrement not null,
                team_id    integer not null,
                season_id  integer not null,
                match_date date,
                week_number integer,
                is_home    tinyint(1) not null default \'1\',
                opponent_name varchar,
                score      varchar,
                result     varchar check (result in (\'Draw\', \'Loss\', \'Win\', \'Withdrawal\')),
                is_bye     tinyint(1) not null default \'0\',
                created_at datetime,
                updated_at datetime,
                foreign key(team_id)   references teams(id)   on delete cascade,
                foreign key(season_id) references seasons(id) on delete cascade
            )
        ');

        DB::statement('INSERT INTO match_results_old SELECT * FROM match_results');
        DB::statement('DROP TABLE match_results');
        DB::statement('ALTER TABLE match_results_old RENAME TO match_results');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function up(): void
    {
        // SQLite does not support DROP CONSTRAINT — rebuild the table without the CHECK.
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE match_results_new (
                id         integer primary key autoincrement not null,
                team_id    integer not null,
                season_id  integer not null,
                match_date date,
                week_number integer,
                is_home    tinyint(1) not null default \'1\',
                opponent_name varchar,
                score      varchar,
                result     varchar,
                is_bye     tinyint(1) not null default \'0\',
                created_at datetime,
                updated_at datetime,
                foreign key(team_id)   references teams(id)   on delete cascade,
                foreign key(season_id) references seasons(id) on delete cascade
            )
        ');

        DB::statement('INSERT INTO match_results_new SELECT * FROM match_results');
        DB::statement('DROP TABLE match_results');
        DB::statement('ALTER TABLE match_results_new RENAME TO match_results');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
