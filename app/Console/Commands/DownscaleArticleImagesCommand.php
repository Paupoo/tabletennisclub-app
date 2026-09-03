<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\ClubPosts\Models\NewsPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bring the article images uploaded before the client-side downscale back to a
 * sane size.
 *
 * New uploads are shrunk in the browser, which never touches what is already
 * on disk. This is the one-shot catch-up for those; it is not scheduled.
 */
final class DownscaleArticleImagesCommand extends Command
{
    /**
     * The article hero on a desktop viewport: a 1088x384 box. `object-cover`
     * centres the image in it, so anything taller than this ratio loses half
     * the overflow off the top — which is where faces sit in a group photo.
     */
    private const float HERO_RATIO = 1088 / 384;

    /**
     * The longest edge any featured image needs. The widest surface that shows
     * one is the article hero, ~1088 CSS pixels — 1600 leaves room for retina
     * screens without serving a print-resolution photo.
     */
    private const int MAX_EDGE = 1600;

    /** Above this much of the image lost off the top, the framing is worth a look. */
    private const float SEVERE_TOP_CROP = 0.20;

    protected $description = 'Redimensionne les images d\'article déjà stockées et signale celles que le héros rogne sévèrement';

    protected $signature = 'articles:downscale-images {--dry-run : Ne rien écrire, se contenter du rapport}';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        /** @var array<int, array{slug: string, topCrop: float}> $severelyCropped */
        $severelyCropped = [];
        $processed = 0;

        foreach (NewsPost::query()->whereNotNull('image')->cursor() as $article) {
            if (! $disk->exists($article->image)) {
                continue;
            }

            $path = $disk->path($article->image);
            $this->downscale($path, $dryRun);
            $processed++;

            $topCrop = $this->topCropFraction($path);

            if ($topCrop > self::SEVERE_TOP_CROP) {
                $severelyCropped[] = ['slug' => $article->slug, 'topCrop' => $topCrop];
            }
        }

        $this->info(sprintf('%d image(s) traitée(s)%s.', $processed, $dryRun ? ' (simulation)' : ''));

        $this->reportSevereCrops($severelyCropped);

        return self::SUCCESS;
    }

    /**
     * Resize a JPEG/PNG/WebP in place so its longest edge is at most MAX_EDGE.
     */
    private function downscale(string $path, bool $dryRun): void
    {
        $size = @getimagesize($path);

        if ($size === false) {
            return;
        }

        [$width, $height] = $size;
        $scale = self::MAX_EDGE / max($width, $height);

        if ($scale >= 1.0 || $dryRun) {
            return;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return;
        }

        $resized = imagescale($source, (int) round($width * $scale), (int) round($height * $scale));

        if ($resized === false) {
            return;
        }

        imagejpeg($resized, $path, 85);
    }

    /**
     * @param  array<int, array{slug: string, topCrop: float}>  $severelyCropped
     */
    private function reportSevereCrops(array $severelyCropped): void
    {
        if ($severelyCropped === []) {
            $this->info('Aucun article dont le héros rogne sévèrement la photo.');

            return;
        }

        usort($severelyCropped, fn (array $a, array $b): int => $b['topCrop'] <=> $a['topCrop']);

        $this->newLine();
        $this->warn('Photos rognées par le haut — vérifiez leur point focal :');

        foreach ($severelyCropped as $entry) {
            $this->line(sprintf('  %s — %d %% masqués en haut', $entry['slug'], round($entry['topCrop'] * 100)));
        }
    }

    /**
     * How much of the image the desktop hero hides off the top.
     *
     * An image wider than the hero is cropped sideways instead, so nothing is
     * lost above — those never need a focal point for the hero.
     */
    private function topCropFraction(string $path): float
    {
        $size = @getimagesize($path);

        if ($size === false || $size[1] === 0) {
            return 0.0;
        }

        $ratio = $size[0] / $size[1];

        if ($ratio >= self::HERO_RATIO) {
            return 0.0;
        }

        return (1 - $ratio / self::HERO_RATIO) / 2;
    }
}
