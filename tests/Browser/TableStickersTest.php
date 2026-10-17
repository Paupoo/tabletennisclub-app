<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;

/*
 * A sticker is five centimetres a side — what fits on the edge of a club table.
 * Only a browser lays the sheet out in real units; and the table form, which a
 * scan opens on a phone in the hall, has to fit a phone.
 */
it('lays each sticker out as a five-centimetre square', function (): void {
    $room = Room::factory()->create();
    Table::factory()->count(2)->create(['room_id' => $room->id]);

    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());

    // 50 mm at the CSS reference of 96 px per inch: 188.98 px.
    $size = visit(route('admin.tables.stickers'))
        ->script("(() => { const r = document.querySelector('[data-sticker]').getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; })()");

    expect($size)->toBe([189, 189]);
})->group('tables');

it('keeps the code, the number and the club name inside each square', function (): void {
    $room = Room::factory()->create();
    Table::factory()->count(4)->create(['room_id' => $room->id]);

    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());

    // Every child of every sticker lies within its square, and the code keeps
    // the bulk of it: under 34 mm (128 px) it reads badly from a phone.
    $spill = visit(route('admin.tables.stickers'))->script(<<<'JS'
        (() => {
            const faults = [];
            document.querySelectorAll('[data-sticker]').forEach((sticker, i) => {
                const box = sticker.getBoundingClientRect();
                sticker.querySelectorAll('*').forEach((child) => {
                    const r = child.getBoundingClientRect();
                    if (r.top < box.top - 0.5 || r.bottom > box.bottom + 0.5 || r.left < box.left - 0.5 || r.right > box.right + 0.5) {
                        faults.push(i + ':' + child.tagName);
                    }
                });
                if (sticker.querySelector('img').getBoundingClientRect().width < 128) {
                    faults.push(i + ':small-code');
                }
            });
            return faults;
        })()
    JS);

    expect($spill)->toBe([]);
})->group('tables');

it('opens the table form on a phone without sideways scrolling', function (): void {
    $table = Table::factory()->create(['room_id' => Room::factory()->create()->id]);

    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());

    $overflow = visit(route('admin.tables.edit', $table))
        ->resize(375, 812)
        ->script('document.documentElement.scrollWidth - document.documentElement.clientWidth');

    expect($overflow)->toBeLessThanOrEqual(0);
})->group('tables');
