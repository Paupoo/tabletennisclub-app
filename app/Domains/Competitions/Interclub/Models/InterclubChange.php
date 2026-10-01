<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\InterclubChangeKind;
use App\Domains\Shared\Enums\InterclubChangeStatus;
use App\Domains\Shared\Enums\InterclubForfeit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change the federation made to one of our fixtures, as the sync saw it.
 *
 * @property int $id
 * @property int $interclub_id
 * @property int|null $interclub_import_id
 * @property InterclubChangeKind $kind
 * @property InterclubForfeit|null $forfeit
 * @property array{start: string|null, address: string|null}|null $before
 * @property array{start: string|null, address: string|null}|null $after
 * @property string|null $group_key
 * @property InterclubChangeStatus $status
 * @property Carbon|null $notified_at
 * @property int|null $notified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Interclub $interclub
 * @property-read InterclubImport|null $import
 */
class InterclubChange extends Model
{
    protected $casts = [
        'after' => 'array',
        'before' => 'array',
        'forfeit' => InterclubForfeit::class,
        'kind' => InterclubChangeKind::class,
        'notified_at' => 'datetime',
        'status' => InterclubChangeStatus::class,
    ];

    protected $fillable = [
        'after',
        'before',
        'forfeit',
        'group_key',
        'interclub_id',
        'interclub_import_id',
        'kind',
        'notified_at',
        'notified_by',
        'status',
    ];

    /**
     * @return BelongsTo<InterclubImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(InterclubImport::class, 'interclub_import_id');
    }

    /**
     * @return BelongsTo<Interclub, $this>
     */
    public function interclub(): BelongsTo
    {
        return $this->belongsTo(Interclub::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_by');
    }
}
