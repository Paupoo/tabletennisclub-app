<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\EventPostStatusEnum;
use App\Models\ClubPosts\EventPost;
use App\Services\EventPostService;
use Illuminate\Validation\ValidationException;

/**
 * Livewire concern that adds event-post management to any admin component.
 *
 * ## Contract
 *
 * The using class MUST:
 *  - Use \Mary\Traits\Toast to call $this->success() / $this->error()
 *  - Implement resolveEventPostData(): array returning:
 *    [
 *        'model'            => Model,          (the source Eloquent model)
 *        'type'             => ClubEventTypeEnum,
 *        'icon'             => string,
 *        'event_date'       => string,          (Y-m-d)
 *        'start_time'       => string,          (H:i:s)
 *        'end_time'         => string|null,
 *        'price'            => string|null,
 *        'max_participants' => int|null,
 *    ]
 *
 * ## Optional hooks
 *
 * Override onEventPostSaved() to run logic after a successful save
 * (e.g. close a modal, refresh a computed property).
 */
trait HasEventPostForm
{
    public string $eventDescription = '';

    public bool $eventFeatured = false;

    public string $eventFeaturedUntil = '';

    public string $eventLocation = '';

    public ?int $eventPostId = null;

    public string $eventStatus = 'DRAFT';

    public string $eventTitle = '';

    // ── Initialisation ────────────────────────────────────────────────────────

    /**
     * Populate form fields from an existing EventPost, or set sensible defaults.
     */
    public function initEventPost(?EventPost $ep, string $defaultTitle = ''): void
    {
        if ($ep) {
            $this->eventPostId = $ep->id;
            $this->eventTitle = $ep->title;
            $this->eventDescription = $ep->description ?? '';
            $this->eventLocation = $ep->location ?? '';
            $this->eventFeatured = (bool) $ep->featured;
            $this->eventFeaturedUntil = $ep->featured_until?->format('Y-m-d') ?? '';
            $this->eventStatus = $ep->status->value;
        } else {
            $this->eventPostId = null;
            $this->eventTitle = $defaultTitle;
            $this->eventDescription = '';
            $this->eventLocation = '';
            $this->eventFeatured = false;
            $this->eventFeaturedUntil = '';
            $this->eventStatus = 'DRAFT';
        }
    }

    // ── Persistence ───────────────────────────────────────────────────────────

    /**
     * Validate and persist the event post with the given status.
     *
     * Delegates model-specific sync data to {@see resolveEventPostData()}.
     */
    public function saveEventPost(string $status = 'draft'): void
    {
        $rules = [
            'eventTitle' => 'required|min:3',
            'eventDescription' => $status === 'published' ? 'required|string|min:10' : 'nullable|string',
            'eventLocation' => 'nullable|string',
        ];

        try {
            $this->validate($rules);
        } catch (ValidationException $e) {
            $this->error(__('Please correct the errors before saving.'));
            throw $e;
        }

        $enumStatus = $status === 'published'
            ? EventPostStatusEnum::PUBLISHED
            : EventPostStatusEnum::DRAFT;

        $resolved = $this->resolveEventPostData();

        $ep = app(EventPostService::class)->upsert(
            model: $resolved['model'],
            type: $resolved['type'],
            title: $this->eventTitle,
            description: $this->eventDescription,
            location: $this->eventLocation,
            featured: $this->eventFeatured,
            status: $enumStatus,
            syncData: [
                'event_date' => $resolved['event_date'],
                'start_time' => $resolved['start_time'],
                'end_time' => $resolved['end_time'] ?? null,
                'price' => $resolved['price'] ?? null,
                'max_participants' => $resolved['max_participants'] ?? null,
                'icon' => $resolved['icon'],
            ],
            existingPostId: $this->eventPostId,
            featuredUntil: $this->eventFeaturedUntil ?: null,
        );

        $this->eventPostId = $ep->id;
        $this->eventStatus = $enumStatus->value;

        $this->success(
            title: $enumStatus === EventPostStatusEnum::PUBLISHED
                ? __('Event published on the website!')
                : __('Event saved as draft.'),
            icon: 'o-globe-alt',
        );

        $this->onEventPostSaved();
    }

    // ── Hooks ─────────────────────────────────────────────────────────────────

    /**
     * Called after a successful save. Override to run post-save logic
     * (e.g. close a modal, refresh a computed property).
     */
    protected function onEventPostSaved(): void
    {
        // no-op by default
    }

    // ── Abstract contract ─────────────────────────────────────────────────────

    /**
     * Return model-specific data for persisting the EventPost.
     *
     * Must be implemented by the using class.
     */
    protected function resolveEventPostData(): array
    {
        throw new \LogicException(
            static::class . ' must implement resolveEventPostData() when using HasEventPostForm.'
        );
    }
}
