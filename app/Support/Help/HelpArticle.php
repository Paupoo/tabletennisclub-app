<?php

declare(strict_types=1);

namespace App\Support\Help;

use Illuminate\Support\Str;

/**
 * One help task, loaded from a markdown file under resources/help/{locale}.
 *
 * The front matter is a deliberately small `key: value` subset: no YAML parser
 * ships with the app, and a help page never needs nesting.
 *
 * @see HelpLibrary for loading and locale resolution
 */
final readonly class HelpArticle
{
    /**
     * @param  string[]  $audience  Role tags from {@see HelpAudience}; empty means everyone.
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public array $audience,
        public int $order,
        public string $markdown,
    ) {}

    public static function fromFile(string $path): self
    {
        [$meta, $body] = self::split((string) file_get_contents($path));
        $slug = basename($path, '.md');

        return new self(
            slug: $slug,
            title: $meta['title'] ?? Str::headline($slug),
            summary: $meta['summary'] ?? '',
            audience: self::tags($meta['audience'] ?? ''),
            order: (int) ($meta['order'] ?? 99),
            markdown: $body,
        );
    }

    public function html(): string
    {
        return Str::markdown($this->markdown);
    }

    /**
     * @param  string[]  $viewerTags
     */
    public function isVisibleTo(array $viewerTags): bool
    {
        return $this->audience === [] || array_intersect($this->audience, $viewerTags) !== [];
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private static function split(string $raw): array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $raw, $matches)) {
            return [[], $raw];
        }

        $meta = [];

        foreach (preg_split('/\R/', $matches[1]) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        return [$meta, $matches[2]];
    }

    /**
     * @return string[]
     */
    private static function tags(string $raw): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $raw))));
    }
}
