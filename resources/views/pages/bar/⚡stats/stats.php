<?php

declare(strict_types=1);

namespace Resources\views\Pages\Bar\Stats;

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| Bar — ce qui se vend
|--------------------------------------------------------------------------
|
| L'écran du comité pour décider des achats : quels produits sortent, lesquels
| dorment et risquent de périmer. Il se lit, il n'écrit rien.
|
| Il vit sous `/bar` sans en prendre le verrou `bar.access` : le comité lit tout
| mais n'a rien à faire au comptoir. Voir routes/bar.php.
|
*/
new class extends Component
{
    use HasBreadcrumbs;

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        // Pas de lien vers le comptoir : le comité n'y a pas accès.
        return Breadcrumb::make()->home()->current(__('Bar sales'));
    }
};
