<?php
declare(strict_types=1);

namespace App\Support;
class Breadcrumb
{
    protected $items = [];
    
    public static function make()
    {
        return new static();
    }
    
    public function add($title, $url = null, $icon = null)
    {
        $this->items[] = compact('title', 'url', 'icon');
        return $this;
    }
    
    public function articles($url = null)
    {
        return $this->add('Articles', $url ?: route('articles.index'), 'home');
    }
    public function home($url = null)
    {
        return $this->add('Admin', $url ?: route('dashboard'), 'home');
    }
    
    public function matches($url = null)
    {
        return $this->add('Matches', $url ?: route('interclubs.index'), 'home');
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
    
    public function tournament($tournament)
    {
        return $this->add($tournament->name, route('tournaments.show', $tournament));
    }
    
    public function current($title)
    {
        return $this->add($title, null);
    }
    
    public function toArray()
    {
        return $this->items;
    }
}