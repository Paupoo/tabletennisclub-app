<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Contact\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property string|null $apply_status
 * @property bool $is_questionnaire
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\Domains\ClubAdmin\Contact\Models\EmailTemplateFactory factory($count = null, $state = [])
 * @method static Builder<static>|EmailTemplate active()
 * @method static Builder<static>|EmailTemplate newModelQuery()
 * @method static Builder<static>|EmailTemplate newQuery()
 * @method static Builder<static>|EmailTemplate query()
 * @method static Builder<static>|EmailTemplate whereApplyStatus($value)
 * @method static Builder<static>|EmailTemplate whereBody($value)
 * @method static Builder<static>|EmailTemplate whereCreatedAt($value)
 * @method static Builder<static>|EmailTemplate whereId($value)
 * @method static Builder<static>|EmailTemplate whereIsActive($value)
 * @method static Builder<static>|EmailTemplate whereIsQuestionnaire($value)
 * @method static Builder<static>|EmailTemplate whereKey($value)
 * @method static Builder<static>|EmailTemplate whereName($value)
 * @method static Builder<static>|EmailTemplate whereSubject($value)
 * @method static Builder<static>|EmailTemplate whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class EmailTemplate extends Model
{
    use HasFactory;

    protected $casts = [
        'is_questionnaire' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'apply_status',
        'is_questionnaire',
        'is_active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
