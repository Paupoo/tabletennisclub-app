<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\NewsPost;
use App\Support\Markdown;

/*
|--------------------------------------------------------------------------
| Stored XSS through article markdown
|--------------------------------------------------------------------------
|
| Article bodies are written as markdown by committee members and rendered
| with `{!! !!}` on the public page. CommonMark defaults to `html_input:
| allow` and `allow_unsafe_links: true`, so raw <script> and javascript:
| URLs went straight through — a stored XSS reachable by any visitor.
|
| `Markdown::safe()` is the single place those two options are set. Help
| centre articles deliberately keep the permissive renderer: their source is
| a .md file in the repository, not user input.
|
*/

describe('Markdown::safe', function (): void {

    it('escapes raw HTML instead of emitting it', function (): void {
        $html = Markdown::safe('<script>alert(1)</script>');

        expect($html)->not->toContain('<script>')
            ->and($html)->toContain('&lt;script&gt;');
    });

    it('drops javascript: URLs from links', function (): void {
        $html = Markdown::safe('[x](javascript:alert(1))');

        expect($html)->not->toContain('href="javascript:');
    });

    it('still renders ordinary markdown', function (): void {
        expect(Markdown::safe('# Title'))->toContain('<h1>Title</h1>');
    });

})->group('security');

describe('public article page', function (): void {

    it('does not emit a script tag smuggled into an article', function (): void {
        $article = NewsPost::factory()->create([
            'content' => "Bonjour\n\n<script>alert(1)</script>",
        ]);

        $this->get(route('public.clubPosts.show', $article->slug))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    });

    it('does not emit a javascript: link smuggled into an article', function (): void {
        $article = NewsPost::factory()->create([
            'content' => '[cliquez ici](javascript:alert(1))',
        ]);

        $this->get(route('public.clubPosts.show', $article->slug))
            ->assertOk()
            ->assertDontSee('href="javascript:', escape: false);
    });

})->group('security');
