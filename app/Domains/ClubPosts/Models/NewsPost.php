<?php

declare(strict_types=1);

namespace App\Domains\ClubPosts\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property int|null $reading_time
 * @property NewsPostCategoryEnum $category
 * @property string|null $image
 * @property int $image_focal_x
 * @property int $image_focal_y
 * @property-read string $image_position
 * @property int $user_id
 * @property NewsPostStatusEnum $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Database\Factories\Domains\ClubPosts\Models\NewsPostFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost search(string $value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsPost withoutTrashed()
 *
 * @mixin \Eloquent
 */
class NewsPost extends Model
{
    use HasAuditLog;
    use HasFactory, SoftDeletes;

    /**
     * Centre the focal point until an author moves it.
     *
     * The column carries the same default, but a model built in PHP never
     * reads it back — without this, a freshly created post would compose
     * `object-position: % %` and every browser would ignore it.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'image_focal_x' => 50,
        'image_focal_y' => 50,
    ];

    protected $casts = [
        'title' => 'string',
        'slug' => 'string',
        'content' => 'string',
        'reading_time' => 'integer',
        'category' => NewsPostCategoryEnum::class,
        'image' => 'string',
        'image_focal_x' => 'integer',
        'image_focal_y' => 'integer',
        'status' => NewsPostStatusEnum::class,
        'user_id' => 'integer',
    ];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'reading_time',
        'category',
        'image',
        'image_focal_x',
        'image_focal_y',
        'status',
        'user_id',
    ];

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The posts the public site may serve. Keep the rule here, in one place:
     * it used to be spelled out in the public list and forgotten entirely in
     * the article page and the home page, which served drafts and archived
     * posts to anonymous visitors.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', NewsPostStatusEnum::PUBLISHED);
    }

    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('title', 'like', '%' . $value . '%')
            ->orWhere('content', 'like', '%' . $value . '%');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (NewsPost $post): void {
            $words = str_word_count(strip_tags($post->content ?? ''));
            $post->reading_time = (int) ceil($words / 225);
        });
    }

    /**
     * The `object-position` value that keeps the subject in frame.
     *
     * The featured image is cropped by `object-cover` in four different
     * container ratios, so the crop is decided at render time. This point is
     * what every one of them keeps visible.
     */
    protected function imagePosition(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->image_focal_x}% {$this->image_focal_y}%");
    }
}
