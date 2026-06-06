<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Livewire\Concerns\HasEventPostForm;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasEventPostForm, Toast;

    // ── Model reference (serialized as strings for Livewire hydration) ────
    #[Locked]
    public string $modelClass;

    #[Locked]
    public int $modelId;

    // ── Sync data (pre-resolved by the caller) ────────────────────────────
    #[Locked]
    public string $eventType;

    #[Locked]
    public string $icon;

    public ?string $eventDate = null;

    public ?string $startTime = null;

    public ?string $endTime = null;

    public ?string $price = null;

    public ?int $maxParticipants = null;

    public string $defaultTitle = '';

    // ── Gate ──────────────────────────────────────────────────────────────
    #[Locked]
    public bool $canPublish = true;

    #[Locked]
    public ?string $cannotPublishReason = null;

    // ── State ─────────────────────────────────────────────────────────────
    public bool $showModal = false;

    public function mount(): void
    {
        /** @var Model $model */
        $model = ($this->modelClass)::with('eventPost')->findOrFail($this->modelId);

        /** @var EventPost|null $ep */
        $ep = $model->eventPost;

        $this->initEventPost($ep, $this->defaultTitle);
    }

    public function open(): void
    {
        if (! $this->canPublish) {
            return;
        }

        $this->showModal = true;
    }

    protected function resolveEventPostData(): array
    {
        /** @var Model $model */
        $model = ($this->modelClass)::findOrFail($this->modelId);

        return [
            'model'            => $model,
            'type'             => ClubEventTypeEnum::from($this->eventType),
            'icon'             => $this->icon,
            'event_date'       => $this->eventDate ?? now()->toDateString(),
            'start_time'       => $this->startTime ?? '00:00:00',
            'end_time'         => $this->endTime,
            'price'            => $this->price,
            'max_participants' => $this->maxParticipants,
        ];
    }

    protected function onEventPostSaved(): void
    {
        $this->showModal = false;
        $this->dispatch('event-post-saved');
    }

    public function render(): \Illuminate\View\View
    {
        return view('components.admin.shared.⚡event-post-button');
    }
};
?>

<div>
    {{-- ── Trigger button ──────────────────────────────────────────────── --}}
    <x-button
        @class([
            'btn-ghost btn-sm btn-circle',
            'text-success'                  => $eventStatus === 'PUBLISHED',
            'text-warning'                  => $eventPostId !== null && $eventStatus !== 'PUBLISHED',
            'opacity-30 cursor-not-allowed' => ! $canPublish,
        ])
        icon="o-globe-alt"
        :tooltip="! $canPublish
            ? ($cannotPublishReason ?? __('Cannot publish'))
            : ($eventPostId ? __('Manage web event') : __('Publish on website'))"
        wire:click="{{ $canPublish ? 'open' : '' }}"
    />

    {{-- ── Modal ───────────────────────────────────────────────────────── --}}
    <x-modal :title="__('Publish on website')" wire:model="showModal" separator>

        <x-admin.shared.event-post-form
            :event-post-id="$eventPostId"
            :event-status="$eventStatus"
        />

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$toggle('showModal')" />
            <x-button
                class="btn-ghost"
                icon="o-document-text"
                :label="__('Save as draft')"
                spinner="saveEventPost"
                wire:click="saveEventPost('draft')"
            />
            <x-button
                class="btn-primary"
                icon="o-globe-alt"
                :label="__('Publish')"
                spinner="saveEventPost"
                wire:click="saveEventPost('published')"
            />
        </x-slot:actions>

    </x-modal>
</div>
