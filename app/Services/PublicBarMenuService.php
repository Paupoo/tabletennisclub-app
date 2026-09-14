<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Bar\Models\BarProduct;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Support\Sponsors;
use Illuminate\Support\Collection;

/**
 * La carte du bar telle que le public la lit.
 *
 * Une seule lecture sert les trois surfaces — l'écran casté, la page scannée
 * au QR et la feuille posée sur les tables — pour qu'aucune ne puisse annoncer
 * un prix que les deux autres ignorent.
 *
 * Elle ne lit que ce que la caisse tient déjà : ni description, ni image, ni
 * ordre d'affichage n'ont été ajoutés au catalogue. Ce qui se décide ici se
 * décide donc à la lecture.
 */
class PublicBarMenuService
{
    /**
     * La catégorie qui se montre en vedette plutôt qu'en liste.
     *
     * C'est la seule rotation du bar, donc la seule raison de relever les yeux
     * vers l'écran quand on connaît déjà la carte. Une catégorie, pas une
     * colonne : la bière élue quitte la liste des bières pour l'encart, et
     * n'est jamais écrite deux fois.
     */
    public const string FEATURED_CATEGORY = 'Bière du mois';

    /** Ce qu'une surface affiche à côté de la carte, sans jamais le saisir deux fois. */
    private const int ANNOUNCEMENTS = 2;

    private const int NEWS = 2;

    /**
     * @return array{
     *     categories: list<array{name: string, products: list<array{name: string, price: string, out: bool}>}>,
     *     featured: array{name: string, price: string}|null,
     *     announcements: list<array{title: string, icon: string|null, when: string, where: string|null, price: string|null}>,
     *     news: list<array{title: string, category: string}>,
     *     sponsors: list<array{name: string, logo: string|null, url: string|null}>
     * }
     */
    public function menu(): array
    {
        $products = BarProduct::query()
            ->where('is_available', true)
            ->withStock()
            ->with('category')
            ->orderBy('name')
            ->get();

        return [
            'categories' => $this->categories($products),
            'featured' => $this->featured($products),
            'announcements' => $this->announcements(),
            'news' => $this->news(),
            'sponsors' => Sponsors::all(),
        ];
    }

    /**
     * Le prix tel qu'une carte l'écrit : « 2 € », « 2,50 € ».
     *
     * Deux écarts assumés avec le `euros()` de la caisse. Les décimales nulles
     * tombent, parce qu'une carte de bar n'écrit pas « 2,00 € ». Et l'espace
     * est insécable : mesuré, un prix qui casse avant le « € » fait perdre
     * deux crans de taille à toute la carte affichée à l'écran.
     */
    public function price(int $cents): string
    {
        $amount = $cents % 100 === 0
            ? (string) intdiv($cents, 100)
            : number_format($cents / 100, 2, ',', '');

        return $amount . "\u{A0}€";
    }

    /**
     * Ce qui arrive au club, mis en avant d'abord.
     *
     * Rien à ressaisir pour le bar : l'événement publié une fois sur le site
     * s'affiche ici, et sort tout seul le lendemain de sa date. Le drapeau
     * `featured`, qui existe déjà, sert de « passe ça au bar ».
     *
     * @return list<array{title: string, icon: string|null, when: string, where: string|null, price: string|null}>
     */
    private function announcements(): array
    {
        return EventPost::query()
            ->published()
            ->upcoming()
            ->orderByDesc('featured')
            ->orderBy('event_date')
            ->take(self::ANNOUNCEMENTS)
            ->get()
            ->map(fn (EventPost $event): array => [
                'title' => $event->title,
                'icon' => $event->icon,
                'when' => $event->event_date->translatedFormat('l j F'),
                'where' => $event->location,
                'price' => $event->price,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, BarProduct>  $products
     * @return list<array{name: string, products: list<array{name: string, price: string, out: bool}>}>
     */
    private function categories(Collection $products): array
    {
        return $products
            ->reject(fn (BarProduct $product): bool => $this->isFeatured($product))
            ->groupBy(fn (BarProduct $product): string => $product->category->name)
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'products' => $group->map(fn (BarProduct $product): array => [
                    'name' => $product->name,
                    'price' => $this->price($product->sale_price),
                    'out' => $product->stock <= 0,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * La bière du mois, si elle est servable.
     *
     * Épuisée, l'encart disparaît au lieu de se griser : mettre en vedette ce
     * qu'on ne peut pas servir vend une déception.
     *
     * @param  Collection<int, BarProduct>  $products
     * @return array{name: string, price: string}|null
     */
    private function featured(Collection $products): ?array
    {
        $beer = $products
            ->filter(fn (BarProduct $product): bool => $this->isFeatured($product))
            ->first(fn (BarProduct $product): bool => $product->stock > 0);

        if ($beer === null) {
            return null;
        }

        return [
            'name' => $beer->name,
            'price' => $this->price($beer->sale_price),
        ];
    }

    private function isFeatured(BarProduct $product): bool
    {
        return $product->category->name === self::FEATURED_CATEGORY;
    }

    /**
     * @return list<array{title: string, category: string}>
     */
    private function news(): array
    {
        return NewsPost::query()
            ->published()
            ->latest()
            ->take(self::NEWS)
            ->get()
            ->map(fn (NewsPost $post): array => [
                'title' => $post->title,
                // La catégorie est traduite ici : au bar, elle est lue par un
                // visiteur, pas par le back-office qui l'a saisie.
                'category' => $post->category->getLabel(),
            ])
            ->all();
    }
}
