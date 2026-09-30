<?php

declare(strict_types=1);

namespace App\Console\Commands\ClubPosts;

use App\Domains\ClubPosts\Models\NewsPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the images put in an article body that no text points to any more.
 *
 * The editor files an image the moment it is inserted, before the article is
 * saved; removing it from the text, abandoning the draft or deleting the
 * article leaves the file behind. A file is only deleted once no stored text
 * mentions it — articles soft-deleted included, since they can come back, and
 * every other field written in the editor, where an image can be pasted — and
 * after a week, so an article still being written keeps its images.
 */
#[Signature('articles:prune-content-images
    {--days=7 : How long an unreferenced image is kept}
    {--dry-run : List what would be deleted, delete nothing}')]
#[Description('Delete article body images that no stored text refers to any more')]
class PruneArticleContentImagesCommand extends Command
{
    /**
     * Every column written in <x-markdown-editor>.
     *
     * @var array<string, string> table => column
     */
    private const array MARKDOWN_COLUMNS = [
        'news_posts' => 'content',
        'communications' => 'body',
        'email_templates' => 'body',
        'meetings' => 'description',
        'meeting_minutes' => 'notes',
    ];

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subDays(max(1, (int) $this->option('days')))->getTimestamp();
        $referenced = $this->referencedFilenames();
        $pruned = [];

        foreach ($disk->files(NewsPost::CONTENT_IMAGES_DIRECTORY) as $path) {
            if (isset($referenced[basename($path)]) || $disk->lastModified($path) >= $cutoff) {
                continue;
            }

            if (! $this->option('dry-run')) {
                $disk->delete($path);
            }

            $pruned[] = $path;
        }

        foreach ($pruned as $path) {
            $this->line('  ' . $path);
        }

        $this->components->info(trans_choice(
            $this->option('dry-run')
                ? '{0}No article image to prune.|[1,*]:count article image(s) would be pruned.'
                : '{0}No article image to prune.|[1,*]:count article image(s) pruned.',
            count($pruned),
            ['count' => count($pruned)],
        ));

        return self::SUCCESS;
    }

    /**
     * The file names the stored texts point to, as keys.
     *
     * Read straight from the tables, so soft-deleted rows count too.
     *
     * @return array<string, true>
     */
    private function referencedFilenames(): array
    {
        $pattern = '#' . preg_quote(NewsPost::CONTENT_IMAGES_DIRECTORY, '#') . '/([A-Za-z0-9._-]+)#';
        $referenced = [];

        foreach (self::MARKDOWN_COLUMNS as $table => $column) {
            DB::table($table)
                ->where($column, 'like', '%' . NewsPost::CONTENT_IMAGES_DIRECTORY . '/%')
                ->orderBy('id')
                ->select(['id', $column])
                ->chunkById(200, function ($rows) use ($column, $pattern, &$referenced): void {
                    foreach ($rows as $row) {
                        preg_match_all($pattern, (string) $row->{$column}, $matches);

                        foreach ($matches[1] as $filename) {
                            $referenced[$filename] = true;
                        }
                    }
                });
        }

        return $referenced;
    }
}
