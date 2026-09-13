<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bar;

use App\Actions\Bar\GenerateBarMenuQR;
use App\Http\Controllers\Controller;
use App\Services\PublicBarMenuService;
use Illuminate\Contracts\View\View;

/**
 * La carte du bar, côté visiteur.
 *
 * Publique mais absente du menu du site : on y arrive en scannant le QR posé
 * sur les tables, ou en connaissant l'adresse. Elle vit sous `/la-carte` et
 * non sous `/bar`, déjà pris par la caisse et son back-office.
 *
 * Trois lectures, aucune écriture.
 */
class PublicBarMenuController extends Controller
{
    public function __construct(private readonly PublicBarMenuService $menu) {}

    /**
     * La feuille A6 posée sur les tables : le QR, le lien, rien d'autre.
     *
     * Aucun prix imprimé — une carte imprimée périme au premier changement de
     * prix, et c'est exactement ce que le QR évite.
     */
    public function flyer(GenerateBarMenuQR $qr): View
    {
        $url = route('public.bar.menu');

        return view('public.bar.flyer', [
            'url' => $url,
            'qr' => $qr($url),
        ]);
    }

    /** La page scannée à table. */
    public function index(): View
    {
        return view('public.bar.menu', $this->menu->menu());
    }

    /** L'écran casté derrière le bar. */
    public function screen(): View
    {
        return view('public.bar.screen', $this->menu->menu());
    }
}
