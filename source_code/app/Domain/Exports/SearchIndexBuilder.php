<?php

namespace App\Domain\Exports;

use App\Domain\Content\ContentHtmlRenderer;
use App\Domain\Rendering\SiteContent;

class SearchIndexBuilder
{
    public function __construct(private readonly ContentHtmlRenderer $renderer) {}

    /**
     * @return array<int, array{t: string, u: string, e: string, k: string}>
     */
    public function entries(SiteContent $content): array
    {
        $entries = [];

        foreach ($content->articles as $article) {
            $topic = $article['topic_id'] ? $content->topicBySlugById((int) $article['topic_id']) : null;
            $rendered = $this->renderer->render($article['content'] ?? []);
            $excerpt = trim((string) ($article['excerpt'] ?? ''));
            if ($excerpt === '') {
                $excerpt = mb_substr($rendered->plainText, 0, 220);
            }

            $entries[] = [
                't' => $article['title'],
                'u' => '../articles/'.$article['slug'].'/index.html',
                'e' => mb_substr($excerpt, 0, 260),
                'k' => mb_substr(($topic['title'] ?? '').' '.$rendered->plainText, 0, 900),
            ];
        }

        foreach ($content->topics as $topic) {
            $entries[] = [
                't' => $topic['title'],
                'u' => '../topics/'.$topic['slug'].'/index.html',
                'e' => mb_substr((string) ($topic['description'] ?? ''), 0, 260),
                'k' => $topic['title'],
            ];
        }

        return $entries;
    }

    public function javascript(SiteContent $content): string
    {
        $payload = [
            'generatedAt' => now()->toIso8601String(),
            'release' => $content->release['version'] ?? null,
            'entries' => $this->entries($content),
        ];

        return 'window.ALARIFE_SEARCH_INDEX = '.json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ).";\n";
    }
}
