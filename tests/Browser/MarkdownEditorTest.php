<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Support\Markdown;

/*
 * <x-markdown-editor> (Tiptap) reads and writes markdown. Its markdown is not
 * the author's byte for byte — `*` bullets come back as `-` — but what the
 * public page shows must not change: an article opened, touched and saved
 * again renders to the same HTML.
 */

const RICH_ARTICLE = <<<'MD'
# Grand titre

Un paragraphe avec du **gras**, de l'*italique*, du `code` et un [lien](https://aftt.be).
Une deuxième ligne du même paragraphe.

## Le tournoi

* premier point
* deuxième point
    * sous-point

1. un
2. deux

> Une citation
> sur deux lignes

### Résultats

| Joueur | Points |
| --- | --- |
| Alice | 12 |
| Bob | 9 |

![L'équipe des moins de 15 ans](https://example.test/storage/clubPosts/content/cup.jpg)

---

```
code en bloc
```

Fin avec un retour forcé  
sur la ligne suivante, et des accents : éàü — et un emoji 🏓.
MD;

/** The editor's markdown after one keystroke typed and deleted at the end. */
function editorMarkdownAfterATouch($page): string
{
    $page->wait(1);
    $page->script(<<<'JS'
        (() => {
          const surface = document.querySelector('.markdown-editor-surface');
          surface.focus();
          const end = document.createRange();
          end.selectNodeContents(surface);
          end.collapse(false);
          getSelection().removeAllRanges();
          getSelection().addRange(end);
        })()
    JS);
    $page->keys('.markdown-editor-surface', ['x', 'Backspace']);

    $markdown = $page->script(<<<'JS'
        Livewire.all().find((c) => c.ephemeral && 'contentImage' in c.ephemeral).$wire.content
    JS);

    return is_array($markdown) ? $markdown[0] : $markdown;
}

function normalisedHtml(string $markdown): string
{
    return trim(preg_replace('/>\s+</', '><', preg_replace('/\s+/', ' ', Markdown::safe($markdown))));
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create());
});

it('loads the editor on demand, without JS errors', function (): void {
    $page = visit(route('admin.website.articles.create'))->wait(1);

    $page->assertNoJavaScriptErrors()
        ->assertPresent('.markdown-editor-surface[contenteditable="true"]')
        ->assertPresent('[role="toolbar"] button[aria-label="Gras"]');
});

it('renders a rich article the same after the editor rewrote it', function (): void {
    $article = NewsPost::factory()->create(['content' => RICH_ARTICLE]);

    $rewritten = editorMarkdownAfterATouch(visit(route('admin.website.articles.edit', $article)));

    expect($rewritten)->not->toBe(RICH_ARTICLE, 'the editor did serialise the article')
        ->and(normalisedHtml($rewritten))->toBe(normalisedHtml(RICH_ARTICLE));
});

/*
 * The upload itself is not exercised here: Pest's in-process server hands
 * Livewire's upload endpoint no multipart file, so `_finishUpload` receives an
 * empty list. The server side is covered by ArticleContentImageTest, and the
 * whole path was checked with Playwright against `artisan serve`.
 */
it('asks for a description before an image can be inserted', function (): void {
    $png = storage_path('framework/testing/editor-cup.png');
    @mkdir(dirname($png), 0755, true);
    $canvas = imagecreatetruecolor(40, 30);
    imagepng($canvas, $png);

    $page = visit(route('admin.website.articles.create'))->wait(1);
    $page->attach('.markdown-editor input[type="file"]', $png);
    @unlink($png);

    $page->assertVisible('input[x-ref="altInput"]')
        ->assertButtonDisabled('Insérer')
        ->type('input[x-ref="altInput"]', 'La coupe des moins de 15 ans')
        ->assertButtonEnabled('Insérer')
        ->assertNoJavaScriptErrors();
});

it('turns the selected words into a link', function (): void {
    $article = NewsPost::factory()->create(['content' => 'Le site de la fédération']);

    $page = visit(route('admin.website.articles.edit', $article))->wait(1);
    $page->script(<<<'JS'
        (() => {
          const text = [...document.querySelectorAll('.markdown-editor-surface p')].pop().firstChild;
          const range = document.createRange();
          range.setStart(text, 3);
          range.setEnd(text, 7);
          getSelection().removeAllRanges();
          getSelection().addRange(range);
          document.querySelector('.markdown-editor-surface').focus();
        })()
    JS);
    $page->wait(0.3)
        ->click('[role="toolbar"] button[aria-label="Lien"]')
        ->type('input[x-ref="linkInput"]', 'https://aftt.be')
        ->press('Appliquer')
        ->wait(0.3);

    $markdown = $page->script(<<<'JS'
        Livewire.all().find((c) => c.ephemeral && 'contentImage' in c.ephemeral).$wire.content
    JS);

    expect(is_array($markdown) ? $markdown[0] : $markdown)->toBe('Le [site](https://aftt.be) de la fédération');
});
