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
     * Every column written in <x-markdown-editor>. The minutes' announcements
     * are a JSON list, where a `/` may be stored as `\/`.
     *
     * @var array<string, list<string>> table => columns
     */
    private const array MARKDOWN_COLUMNS = [
        'news_posts' => ['content'],
        'communications' => ['body'],
        'email_templates' => ['body'],
        'meetings' => ['description'],
        'meeting_agenda_items' => ['description', 'discussion'],
        'meeting_decisions' => ['body'],
        'meeting_action_items' => ['description'],
        'meeting_minutes' => ['notes', 'announcements'],
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
        $directory = explode('/', NewsPost::CONTENT_IMAGES_DIRECTORY);
        $pattern = '#' . implode('\\\\?/', array_map(fn (string $part): string => preg_quote($part, '#'), $directory)) . '\\\\?/([A-Za-z0-9._-]+)#';
        $referenced = [];

        foreach (self::MARKDOWN_COLUMNS as $table => $columns) {
            DB::table($table)
                ->where(function ($query) use ($columns, $directory): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%' . end($directory) . '%');
                    }
                })
                ->orderBy('id')
                ->select(['id', ...$columns])
                ->chunkById(200, function ($rows) use ($columns, $pattern, &$referenced): void {
                    foreach ($rows as $row) {
                        foreach ($columns as $column) {
                            preg_match_all($pattern, (string) $row->{$column}, $matches);

                            foreach ($matches[1] as $filename) {
                                $referenced[$filename] = true;
                            }
                        }
                    }
                });
        }

        return $referenced;
    }
}
