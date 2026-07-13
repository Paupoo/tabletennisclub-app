<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\Competitions\Tournament\Models\Tournament;

class Breadcrumb
{
    protected array $items = [];

    public static function make(): Breadcrumb
    {
        return new static;
    }

    public function add(string $label, ?string $link = null, ?string $icon = null): Breadcrumb
    {
        $this->items[] = compact('label', 'link', 'icon');

        return $this;
    }

    public function articles(?string $url = null): Breadcrumb
    {
        return $this->add('Articles', $url ?: route('admin.website.articles.index'), 's-home');
    }

    public function contacts(?string $url = null): Breadcrumb
    {
        return $this->add('Contacts', $url ?: route('admin.website.contacts.index'));
    }

    public function current(string $title): Breadcrumb
    {
        return $this->add($title);
    }

    public function events(?string $url = null): Breadcrumb
    {
        return $this->add('Events', $url ?: route('eventPosts'), 's-home');
    }

    public function home(?string $url = null): Breadcrumb
    {
        return $this->add(__('Admin Panel'), $url ?: route('dashboard'), 's-home');
    }

    public function matches(?string $url = null): Breadcrumb
    {
        return $this->add('Matches', $url ?: route('admin.interclubs.interclubs'), 's-home');
    }

    public function meetings(?string $url = null): Breadcrumb
    {
        return $this->add(__('Meetings'), $url ?: route('admin.meetings.index'), 'o-calendar-days');
    }

    public function profile(?string $url = null): Breadcrumb
    {
        return $this->add('Profile', $url ?: route('profile.edit'));
    }

    public function results(?string $url = null): Breadcrumb
    {
        return $this->add(__('Results'), $url ?: route('admin.interclubs.results'));
    }

    public function rooms(?string $url = null): Breadcrumb
    {
        return $this->add('Rooms', $url ?: route('admin.rooms.index'));
    }

    public function seasons(?string $url = null): Breadcrumb
    {
        return $this->add(__('Seasons'), $url ?: route('admin.seasons.index'), 'o-calendar');
    }

    public function tables(?string $url = null): Breadcrumb
    {
        return $this->add('Tables', $url ?: route('admin.tables.index'));
    }

    public function teams(?string $url = null): Breadcrumb
    {
        return $this->add('Teams', $url ?: route('admin.interclubs.teams'));
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function tournament(Tournament $tournament): Breadcrumb
    {
        return $this->add($tournament->name, route('admin.tournaments.live-center', $tournament));
    }

    public function tournaments(?string $url = null): Breadcrumb
    {
        return $this->add('Tournaments', $url ?: route('admin.tournaments.index'));
    }

    public function trainingPacks(?string $url = null): Breadcrumb
    {
        return $this->add('Training Packs', $url ?: route('admin.trainings.index'));
    }

    public function trainings(?string $url = null): Breadcrumb
    {
        return $this->add('Trainings', $url ?: route('admin.trainings.index'));
    }

    public function users(?string $url = null): Breadcrumb
    {
        return $this->add('Users', $url ?: route('admin.users.index'));
    }

    public function websiteArticles(?string $url = null): Breadcrumb
    {
        return $this->add(__('Articles'), $url ?: route('admin.website.articles.index'));
    }

    public function websiteContacts(?string $url = null): Breadcrumb
    {
        return $this->add(__('Contacts'), $url ?: route('admin.website.contacts.index'));
    }

    public function websiteEvents(?string $url = null): Breadcrumb
    {
        return $this->add(__('Events'), $url ?: route('admin.website.events.index'));
    }

    public function websiteSpams(?string $url = null): Breadcrumb
    {
        return $this->add(__('Spam'), $url ?: route('admin.website.spams.index'));
    }
}
