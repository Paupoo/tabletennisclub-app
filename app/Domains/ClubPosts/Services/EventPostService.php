<?php

declare(strict_types=1);

namespace App\Domains\ClubPosts\Services;

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use Illuminate\Database\Eloquent\Model;

class EventPostService
{
    /**
     * Create or update an EventPost linked to any Eloquent model.
     *
     * The $syncData array must contain fields derived from the source model
     * (dates, times, price, capacity). User-editable fields (title, description,
     * location, featured) are passed as explicit arguments.
     *
     * @param array{
     *     event_date: string,
     *     start_time: string,
     *     end_time: string|null,
     *     price: string|null,
     *     max_participants: int|null,
     *     icon: string,
     * } $syncData
     */
    public function upsert(
        Model $model,
        ClubEventTypeEnum $type,
        string $title,
        ?string $description,
        ?string $location,
        bool $featured,
        EventPostStatusEnum $status,
        array $syncData,
        ?int $existingPostId = null,
        ?string $featuredUntil = null,
    ): EventPost {
        $data = [
            'eventable_type' => $model::class,
            'eventable_id' => $model->getKey(),
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'status' => $status->value,
            'location' => $location ?? '',
            'featured' => $featured,
            'featured_until' => $featuredUntil,
            'event_date' => $syncData['event_date'],
            'start_time' => $syncData['start_time'],
            'end_time' => $syncData['end_time'] ?? null,
            'price' => $syncData['price'] ?? null,
            'max_participants' => $syncData['max_participants'] ?? null,
            'icon' => $syncData['icon'],
        ];

        if ($existingPostId) {
            $ep = EventPost::findOrFail($existingPostId);
            $ep->update($data);

            return $ep->fresh();
        }

        return EventPost::create($data);
    }
}
