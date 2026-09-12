<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Models;

use App\Domains\Shared\Enums\Mutuality;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One insurer's published form, as the club currently holds it.
 *
 * @property int $id
 * @property Mutuality $mutuality
 * @property string $path
 * @property string $original_name
 * @property int $page_count
 * @property array<int, string> $unresolved_fields
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class AttestationTemplate extends Model
{
    use HasAuditLog;

    protected $casts = [
        'mutuality' => Mutuality::class,
        'page_count' => 'integer',
        'unresolved_fields' => 'array',
    ];

    protected $fillable = [
        'mutuality',
        'path',
        'original_name',
        'page_count',
        'unresolved_fields',
        'uploaded_by_user_id',
    ];

    /**
     * Whether this form can still be filled without leaving holes in it.
     *
     * A template whose insurer reworded a label is not "slightly wrong": the
     * value that label positioned would simply not be printed, and the club
     * would seal a document missing an amount or a period.
     */
    public function isUsable(): bool
    {
        return $this->unresolved_fields === [];
    }
}
