<?php

declare(strict_types=1);

namespace App\Support\Help;

/**
 * Loads the help tasks stored as markdown under resources/help/{locale}.
 *
 * One task = one file = one page, written once. Roles filter what a member is
 * shown; they never split the library into per-persona copies.
 */
final class HelpLibrary
{
    /**
     * French is the fallback on purpose, *not* config('app.fallback_locale').
     * That one is 'en', and the app has no English help — falling back to it
     * would resolve to a directory that does not exist.
     */
    public const FALLBACK_LOCALE = 'fr';

    /**
     * @return HelpArticle[] Ordered by front-matter `order`, then title.
     */
    public static function all(?string $locale = null): array
    {
        $files = glob(self::directory($locale) . '/*.md') ?: [];

        $articles = array_map(HelpArticle::fromFile(...), $files);

        usort($articles, fn (HelpArticle $a, HelpArticle $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);

        return $articles;
    }

    public static function find(string $slug, ?string $locale = null): ?HelpArticle
    {
        // Guards against a traversal-shaped slug reaching the filesystem.
        if (preg_match('/\A[a-z0-9-]+\z/', $slug) !== 1) {
            return null;
        }

        $path = self::directory($locale) . '/' . $slug . '.md';

        return is_file($path) ? HelpArticle::fromFile($path) : null;
    }

    /**
     * @param  string[]  $viewerTags
     * @return HelpArticle[]
     */
    public static function visibleTo(array $viewerTags, ?string $locale = null): array
    {
        return array_values(array_filter(
            self::all($locale),
            fn (HelpArticle $article): bool => $article->isVisibleTo($viewerTags),
        ));
    }

    /**
     * Maps an app locale ('fr_BE') onto a help directory ('fr'), falling back to
     * French when that language has no help written yet.
     */
    private static function directory(?string $locale = null): string
    {
        $language = strtok($locale ?? app()->getLocale(), '_');
        $base = resource_path('help');

        return is_dir($base . '/' . $language)
            ? $base . '/' . $language
            : $base . '/' . self::FALLBACK_LOCALE;
    }
}
