<?php
declare(strict_types=1);

namespace App\Livewire\traits;

trait WithBreadcrumbs
{
    public $breadcrumbs = [];

    public function initializeBreadcrumbs()
    {
        $this->breadcrumbs = $this->getBreadcrumbs();
    }

    public function addBreadcrumb($title, $url = null, $icon = null)
    {
        $this->breadcrumbs[] = compact('title', 'url', 'icon');
    }

    public function setBreadcrumbs(array $breadcrumbs)
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    // Méthode à implémenter dans chaque composant
    abstract protected function getBreadcrumbs(): array;
}