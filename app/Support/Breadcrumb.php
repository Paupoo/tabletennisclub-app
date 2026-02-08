<?php

declare(strict_types=1);

namespace App\Support;

class Breadcrumb
{
    protected array $items = [];

    /**
     * @return Breadcrumb
     */
    public static function make(): Breadcrumb
    {
        return new static;
    }

    /**
     * @param $title
     * @param $url
     * @param $icon
     * @return Breadcrumb
     */
    public function add($title, $url = null, $icon = null): Breadcrumb
    {
        $this->items[] = compact('title', 'url', 'icon');

        return $this;
    }

    /**
     * @param $url
     * @return Breadcrumb
     */
    public function articles($url = null): Breadcrumb
    {
        return $this->add('Articles', $url ?: route('clubPosts.newsPosts.index'), 'home');
    }

    /**
     * @param $url
     * @return Breadcrumb
     */
    public function contacts($url = null): Breadcrumb
    {
        return $this->add('Contacts', $url ?: route('clubAdmin.contacts.index'));
    }

    public function current($title): Breadcrumb
    {
        return $this->add($title);
    }

    public function events($url = null): Breadcrumb
    {
        return $this->add('Events', $url ?: route('clubPosts.eventPosts.index'), 'home');
    }

    public function home($url = null): Breadcrumb
    {
        return $this->add('Admin', $url ?: route('dashboard'), 'home');
    }

    public function matches($url = null): Breadcrumb
    {
        return $this->add('Matches', $url ?: route('interclubs.index'), 'home');
    }

    public function profile($url = null): Breadcrumb
    {
        return $this->add('Profile', $url ?: route('profile.edit'));
    }

    public function rooms($url = null): Breadcrumb
    {
        return $this->add('Rooms', $url ?: route('rooms.index'));
    }
    public function seasons($url = null): Breadcrumb
    {
        return $this->add(__('Seasons'), $url ?: route('admin.seasons.index'), 'calendar');
    }

    public function subscriptions($url = null): Breadcrumb
    {
        return $this->add(__('Subscriptions'), $url ?: route('admin.subscriptions.index'), 'calendar');
    }

    public function tables($url = null): Breadcrumb
    {
        return $this->add('Tables', $url ?: route('tables.index'));
    }

    public function teams($url = null): Breadcrumb
    {
        return $this->add('Teams', $url ?: route('teams.index'));
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function tournament($tournament): Breadcrumb
    {
        return $this->add($tournament->name, route('tournaments.show', $tournament));
    }

    public function tournaments($url = null): Breadcrumb
    {
        return $this->add('Tournaments', $url ?: route('tournaments.index'));
    }

    public function trainingPacks($url = null): Breadcrumb
    {
        return $this->add('Training Packs', $url ?: route('admin.trainingpacks.index'));
    }

    public function trainings($url = null): Breadcrumb
    {
        return $this->add('Trainings', $url ?: route('trainings.index'));
    }

    public function users($url = null)
    {
        return $this->add('Users', $url ?: route('users.index'));
    }
}
