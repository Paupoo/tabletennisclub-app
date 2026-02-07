<?php

declare(strict_types=1);

namespace App\Support;

class Breadcrumb
{
    protected array $items = [];

    /**
     * @return static
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * @param $title
     * @param $url
     * @param $icon
     * @return $this
     */
    public function add($title, $url = null, $icon = null): self
    {
        $this->items[] = compact('title', 'url', 'icon');

        return $this;
    }

    /**
     * @param $url
     * @return $this
     */
    public function articles($url = null): self
    {
        return $this->add('Articles', $url ?: route('clubPosts.newsPosts.index'), 'home');
    }

    /**
     * @param $url
     * @return self
     */
    public function contacts($url = null): self
    {
        return $this->add('Contacts', $url ?: route('clubAdmin.contacts.index'));
    }

    public function current($title)
    {
        return $this->add($title);
    }

    public function events($url = null)
    {
        return $this->add('Events', $url ?: route('clubPosts.eventPosts.index'), 'home');
    }

    public function home($url = null)
    {
        return $this->add('Admin', $url ?: route('dashboard'), 'home');
    }

    public function matches($url = null)
    {
        return $this->add('Matches', $url ?: route('interclubs.index'), 'home');
    }

    public function profile($url = null)
    {
        return $this->add('Profile', $url ?: route('profile.edit'));
    }

    public function rooms($url = null)
    {
        return $this->add('Rooms', $url ?: route('rooms.index'));
    }

    public function tables($url = null)
    {
        return $this->add('Tables', $url ?: route('tables.index'));
    }

    public function teams($url = null)
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

    public function tournament($tournament)
    {
        return $this->add($tournament->name, route('tournaments.show', $tournament));
    }

    public function tournaments($url = null)
    {
        return $this->add('Tournaments', $url ?: route('tournaments.index'));
    }

    public function trainings($url = null)
    {
        return $this->add('Trainings', $url ?: route('trainings.index'));
    }

    public function users($url = null)
    {
        return $this->add('Users', $url ?: route('users.index'));
    }
}
