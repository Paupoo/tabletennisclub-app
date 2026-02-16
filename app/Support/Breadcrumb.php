<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ClubEvents\Tournament\Tournament;

class Breadcrumb
{
    protected array $items = [];

    public static function make(): Breadcrumb
    {
        return new static;
    }

    public function add(string $title, ?string $url = null, ?string $icon = null): Breadcrumb
    {
        $this->items[] = compact('title', 'url', 'icon');

        return $this;
    }

    public function articles(?string $url = null): Breadcrumb
    {
        return $this->add('Articles', $url ?: route('clubPosts.newsPosts.index'), 'home');
    }

    public function contacts(?string $url = null): Breadcrumb
    {
        return $this->add('Contacts', $url ?: route('clubAdmin.contacts.index'));
    }

    public function current(string $title): Breadcrumb
    {
        return $this->add($title);
    }

    public function events(?string $url = null): Breadcrumb
    {
        return $this->add('Events', $url ?: route('clubPosts.eventPosts.index'), 'home');
    }

    public function home(?string $url = null): Breadcrumb
    {
        return $this->add('Admin', $url ?: route('dashboard'), 'home');
    }

    public function matches(?string $url = null): Breadcrumb
    {
        return $this->add('Matches', $url ?: route('interclubs.index'), 'home');
    }

    public function profile(?string $url = null): Breadcrumb
    {
        return $this->add('Profile', $url ?: route('profile.edit'));
    }

    public function rooms(?string $url = null): Breadcrumb
    {
        return $this->add('Rooms', $url ?: route('rooms.index'));
    }

    public function seasons(?string $url = null): Breadcrumb
    {
        return $this->add(__('Seasons'), $url ?: route('clubEvents.interclubs.seasons.index'), 'calendar');
    }

    public function subscriptions(?string $url = null): Breadcrumb
    {
        return $this->add(__('Subscriptions'), $url ?: route('clubAdmin.subscriptions.index'), 'calendar');
    }

    public function tables(?string $url = null): Breadcrumb
    {
        return $this->add('Tables', $url ?: route('tables.index'));
    }

    public function teams(?string $url = null): Breadcrumb
    {
        return $this->add('Teams', $url ?: route('teams.index'));
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function tournament(Tournament $tournament): Breadcrumb
    {
        return $this->add($tournament->name, route('tournaments.show', $tournament));
    }

    public function tournaments(?string $url = null): Breadcrumb
    {
        return $this->add('Tournaments', $url ?: route('tournaments.index'));
    }

    public function trainingPacks(?string $url = null): Breadcrumb
    {
        return $this->add('Training Packs', $url ?: route('admin.trainingpacks.index'));
    }

    public function trainings(?string $url = null): Breadcrumb
    {
        return $this->add('Trainings', $url ?: route('trainings.index'));
    }

    public function users(?string $url = null): Breadcrumb
    {
        return $this->add('Users', $url ?: route('users.index'));
    }
}
