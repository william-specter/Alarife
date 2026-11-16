<?php

namespace Tests\Unit;

use App\Domain\Content\ContentHtmlRenderer;
use App\Domain\Content\ContentValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentHtmlRendererTest extends TestCase
{
    private ContentHtmlRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ContentHtmlRenderer;
    }

    public function test_it_renders_structured_content_and_extracts_an_outline(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'History & Origins']]],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'The '],
                    ['type' => 'text', 'text' => 'Nile', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' shaped a civilisation.'],
                ]],
            ],
        ];

        $rendered = $this->renderer->render($doc);

        $this->assertStringContainsString('<h2 id="history-origins">', $rendered->html);
        $this->assertStringContainsString('&amp;', $rendered->html);
        $this->assertStringContainsString('<strong>Nile</strong>', $rendered->html);
        $this->assertSame('History & Origins', $rendered->toc[0]['text']);
        $this->assertGreaterThan(5, $rendered->words);
    }

    public function test_it_escapes_text_and_rejects_unsafe_link_schemes(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Click ', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
                    ['type' => 'text', 'text' => '<script>alert(1)</script>'],
                ]],
            ],
        ];

        $html = $this->renderer->render($doc)->html;

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_it_drops_unknown_nodes(): void
    {
        $doc = ['type' => 'doc', 'content' => [
            ['type' => 'marquee', 'content' => [['type' => 'text', 'text' => 'unsafe']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'safe']]],
        ]];

        $html = $this->renderer->render($doc)->html;

        $this->assertStringNotContainsString('marquee', $html);
        $this->assertStringContainsString('safe', $html);
    }

    public function test_it_renders_images_through_the_media_resolver(): void
    {
        $doc = ['type' => 'doc', 'content' => [
            ['type' => 'image', 'attrs' => ['mediaId' => 7, 'alt' => 'A photograph', 'caption' => 'A caption']],
            ['type' => 'gallery', 'attrs' => ['images' => [
                ['mediaId' => 8, 'alt' => 'Second'],
            ]]],
        ]];

        $html = $this->renderer->render($doc, [
            'media' => fn (int $id, ?string $src) => "assets/images/{$id}.jpg",
        ])->html;

        $this->assertStringContainsString('assets/images/7.jpg', $html);
        $this->assertStringContainsString('<figcaption>A caption</figcaption>', $html);
        $this->assertStringContainsString('assets/images/8.jpg', $html);
    }

    public function test_validation_rejects_unknown_blocks_and_oversized_documents(): void
    {
        $validator = new ContentValidator;

        try {
            $validator->validate(['type' => 'doc', 'content' => [['type' => 'script']]]);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $validator->validate(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('x', 2_100_000)]]],
        ]]);
    }
}
