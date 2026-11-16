<?php

namespace App\Domain\Content;

use Illuminate\Support\Str;

/**
 * Deterministic, allow-listed Tiptap JSON → semantic HTML renderer.
 *
 * Raw HTML is never accepted. Unknown nodes are ignored. All text is escaped.
 */
class ContentHtmlRenderer
{
    public const MAX_BYTES = 2_097_152;

    public const MAX_DEPTH = 40;

    public const MAX_IMAGES = 500;

    /** @var array<string, int> */
    private array $headingCounts = [];

    private int $imageCount = 0;

    private int $wordCount = 0;

    private array $toc = [];

    /** @var array<int, string> */
    private array $plainParts = [];

    /** @var callable(int, ?string): ?string */
    private $mediaResolver;

    /** @var callable(string): string */
    private $linkRewriter;

    public function __construct()
    {
        $this->mediaResolver = fn (int $id, ?string $src) => $src;
        $this->linkRewriter = fn (string $href) => $href;
    }

    /**
     * @param  array{media?: callable(int, ?string): ?string, links?: callable(string): string}  $options
     */
    public function render(?array $doc, array $options = []): RenderedContent
    {
        $this->headingCounts = [];
        $this->imageCount = 0;
        $this->wordCount = 0;
        $this->toc = [];
        $this->plainParts = [];
        $this->mediaResolver = $options['media'] ?? fn (int $id, ?string $src) => $src;
        $this->linkRewriter = $options['links'] ?? fn (string $href) => $href;

        if (! is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
            return new RenderedContent('', [], 0, '');
        }

        $html = $this->renderChildren($doc);

        return new RenderedContent(
            html: $html,
            toc: $this->toc,
            words: $this->wordCount,
            plainText: trim(implode(' ', $this->plainParts)),
        );
    }

    public function wordCount(?array $doc): int
    {
        return $this->render($doc)->words;
    }

    /** @return array<int, array{id: string, text: string, level: int}> */
    public function outline(?array $doc): array
    {
        return $this->render($doc)->toc;
    }

    private function renderChildren(array $node, int $depth = 0): string
    {
        if ($depth > self::MAX_DEPTH) {
            return '';
        }

        $html = '';
        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $html .= $this->renderNode($child, $depth + 1);
            }
        }

        return $html;
    }

    private function renderNode(array $node, int $depth): string
    {
        $type = $node['type'] ?? '';
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        return match ($type) {
            'paragraph' => $this->renderParagraph($node, $depth, $attrs),
            'heading' => $this->renderHeading($node, $attrs, $depth),
            'bulletList' => '<ul>'.$this->renderChildren($node, $depth).'</ul>',
            'orderedList' => $this->renderOrderedList($node, $attrs, $depth),
            'listItem' => '<li>'.$this->renderChildren($node, $depth).'</li>',
            'blockquote' => '<blockquote>'.$this->renderChildren($node, $depth).'</blockquote>',
            'codeBlock' => $this->renderCodeBlock($node, $attrs),
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'text' => $this->renderText($node, $attrs),
            'image' => $this->renderImage($node, $attrs),
            'gallery' => $this->renderGallery($attrs),
            'table' => '<div class="table-wrap"><table>'.$this->renderChildren($node, $depth).'</table></div>',
            'taskList' => '<ul class="task-list">'.$this->renderChildren($node, $depth).'</ul>',
            'taskItem' => $this->renderTaskItem($node, $depth, $attrs),
            'tableRow' => '<tr>'.$this->renderChildren($node, $depth).'</tr>',
            'tableHeader' => $this->renderTableHeader($node, $attrs, $depth),
            'tableCell' => $this->renderTableCell($node, $attrs, $depth),
            'callout' => $this->renderCallout($node, $attrs, $depth),
            'citation' => $this->renderCitation($attrs, $depth),
            'timeline' => $this->renderTimeline($attrs),
            'doc' => $this->renderChildren($node, $depth),
            default => '',
        };
    }

    private function renderParagraph(array $node, int $depth, array $attrs): string
    {
        $align = $this->alignmentStyle($attrs);

        return '<p'.$align.'>'.$this->renderChildren($node, $depth).'</p>';
    }

    private function renderHeading(array $node, array $attrs, int $depth): string
    {
        $level = (int) ($attrs['level'] ?? 2);
        $level = min(4, max(2, $level));
        $text = $this->plainText($node);
        $id = $this->uniqueHeadingId($text);
        $align = $this->alignmentStyle($attrs);

        if ($text !== '') {
            $this->toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
        }

        return sprintf(
            '<h%d id="%s"%s>%s<a class="heading-anchor" href="#%s" aria-hidden="true">#</a></h%d>',
            $level,
            e($id),
            $align,
            $this->renderChildren($node, $depth),
            e($id),
            $level,
        );
    }

    private function renderTaskItem(array $node, int $depth, array $attrs): string
    {
        $checked = ! empty($attrs['checked']);
        $mark = $checked ? '☑' : '☐';

        return '<li class="task-item'.($checked ? ' is-checked' : '').'"><span class="task-item__mark" aria-hidden="true">'.$mark.'</span> '
            .$this->renderChildren($node, $depth).'</li>';
    }

    private function alignmentStyle(array $attrs): string
    {
        $align = $attrs['textAlign'] ?? null;

        return in_array($align, ['left', 'center', 'right', 'justify'], true)
            ? ' style="text-align:'.$align.'"'
            : '';
    }

    private function renderOrderedList(array $node, array $attrs, int $depth): string
    {
        $start = isset($attrs['start']) ? (int) $attrs['start'] : 1;

        return '<ol'.($start !== 1 ? ' start="'.$start.'"' : '').'>'.$this->renderChildren($node, $depth).'</ol>';
    }

    private function renderCodeBlock(array $node, array $attrs): string
    {
        $language = preg_replace('/[^a-zA-Z0-9_+-]/', '', (string) ($attrs['language'] ?? ''));
        $code = $this->plainText($node);

        return '<pre><code'.($language !== '' ? ' class="language-'.e($language).'"' : '').'>'.e($code).'</code></pre>';
    }

    private function renderText(array $node, array $attrs): string
    {
        $text = (string) ($node['text'] ?? '');
        $this->countWords($text);
        $this->plainParts[] = $text;
        $html = e($text);

        foreach ($node['marks'] ?? [] as $mark) {
            $markType = $mark['type'] ?? '';
            $markAttrs = $mark['attrs'] ?? [];
            $html = match ($markType) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'underline' => '<u>'.$html.'</u>',
                'strike' => '<s>'.$html.'</s>',
                'code' => '<code>'.$html.'</code>',
                'highlight' => '<mark>'.$html.'</mark>',
                'link' => $this->renderLink($markAttrs, $html),
                default => $html,
            };
        }

        return $html;
    }

    private function renderLink(array $attrs, string $inner): string
    {
        $href = $this->safeHref((string) ($attrs['href'] ?? ''));
        if ($href === null) {
            return $inner;
        }

        $href = ($this->linkRewriter)($href);
        $external = str_starts_with($href, 'http://') || str_starts_with($href, 'https://');
        $target = $attrs['target'] ?? null;

        return sprintf(
            '<a href="%s"%s%s>%s</a>',
            e($href),
            $external ? ' rel="noopener noreferrer"' : '',
            $external || $target === '_blank' ? ' target="_blank"' : '',
            $inner,
        );
    }

    private function renderImage(array $node, array $attrs): string
    {
        if ($this->imageCount >= self::MAX_IMAGES) {
            return '';
        }

        $mediaId = isset($attrs['mediaId']) ? (int) $attrs['mediaId'] : 0;
        $src = $this->resolveMedia($mediaId, isset($attrs['src']) ? (string) $attrs['src'] : null);
        if ($src === null) {
            return '';
        }

        $this->imageCount++;
        $alt = (string) ($attrs['alt'] ?? '');
        $caption = (string) ($attrs['caption'] ?? '');
        $width = isset($attrs['width']) ? max(1, min(100, (int) $attrs['width'])) : null;

        $html = '<figure class="content-image">';
        $html .= sprintf(
            '<img src="%s" alt="%s" loading="lazy" decoding="async"%s>',
            e($src),
            e($alt),
            $width ? ' style="width:'.$width.'%"' : '',
        );
        if ($caption !== '') {
            $html .= '<figcaption>'.e($caption).'</figcaption>';
        }

        return $html.'</figure>';
    }

    private function renderGallery(array $attrs): string
    {
        $images = is_array($attrs['images'] ?? null) ? $attrs['images'] : [];
        $layout = (string) ($attrs['layout'] ?? 'grid');
        if (! in_array($layout, ['grid', 'strip'], true)) {
            $layout = 'grid';
        }
        $items = '';

        foreach ($images as $image) {
            if (! is_array($image) || $this->imageCount >= self::MAX_IMAGES) {
                continue;
            }
            $src = $this->resolveMedia(
                isset($image['mediaId']) ? (int) $image['mediaId'] : 0,
                isset($image['src']) ? (string) $image['src'] : null,
            );
            if ($src === null) {
                continue;
            }
            $this->imageCount++;
            $caption = (string) ($image['caption'] ?? '');
            $items .= '<figure><img src="'.e($src).'" alt="'.e((string) ($image['alt'] ?? '')).'" loading="lazy" decoding="async">';
            $items .= $caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '';
            $items .= '</figure>';
        }

        if ($items === '') {
            return '';
        }

        return '<div class="content-gallery content-gallery--'.$layout.'">'.$items.'</div>';
    }

    private function renderTableHeader(array $node, array $attrs, int $depth): string
    {
        return $this->tableCellTag('th', $node, $attrs, $depth, ' scope="col"');
    }

    private function renderTableCell(array $node, array $attrs, int $depth): string
    {
        return $this->tableCellTag('td', $node, $attrs, $depth, '');
    }

    private function tableCellTag(string $tag, array $node, array $attrs, int $depth, string $extra): string
    {
        $span = '';
        if (isset($attrs['colspan']) && (int) $attrs['colspan'] > 1) {
            $span .= ' colspan="'.(int) $attrs['colspan'].'"';
        }
        if (isset($attrs['rowspan']) && (int) $attrs['rowspan'] > 1) {
            $span .= ' rowspan="'.(int) $attrs['rowspan'].'"';
        }

        return '<'.$tag.$extra.$span.'>'.$this->renderChildren($node, $depth).'</'.$tag.'>';
    }

    private function renderCallout(array $node, array $attrs, int $depth): string
    {
        $tone = in_array($attrs['tone'] ?? 'note', ['note', 'tip', 'warning', 'important'], true)
            ? $attrs['tone']
            : 'note';
        $title = trim((string) ($attrs['title'] ?? ''));
        $html = '<aside class="callout callout--'.$tone.'">';
        if ($title !== '') {
            $html .= '<p class="callout__title">'.e($title).'</p>';
        }

        return $html.$this->renderChildren($node, $depth).'</aside>';
    }

    private function renderCitation(array $attrs, int $depth): string
    {
        $source = trim((string) ($attrs['source'] ?? ''));
        if ($source === '') {
            return '';
        }

        $url = $this->safeHref((string) ($attrs['url'] ?? ''));
        $accessed = trim((string) ($attrs['accessed'] ?? ''));
        $html = '<p class="citation"><span class="citation__label">Source</span> '.e($source);
        if ($url !== null) {
            $url = ($this->linkRewriter)($url);
            $html .= ' — <a href="'.e($url).'" rel="noopener noreferrer">'.e(parse_url($url, PHP_URL_HOST) ?: $url).'</a>';
        }
        if ($accessed !== '') {
            $html .= ' <span class="citation__date">(accessed '.e($accessed).')</span>';
        }

        return $html.'</p>';
    }

    private function renderTimeline(array $attrs): string
    {
        $items = is_array($attrs['items'] ?? null) ? $attrs['items'] : [];
        $html = '<ol class="timeline">';
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $html .= '<li><span class="timeline__date">'.e((string) ($item['date'] ?? '')).'</span>';
            $html .= '<strong>'.e((string) ($item['title'] ?? '')).'</strong>';
            if (! empty($item['body'])) {
                $html .= '<p>'.e((string) $item['body']).'</p>';
            }
            $html .= '</li>';
        }

        return $html.'</ol>';
    }

    private function resolveMedia(int $mediaId, ?string $src): ?string
    {
        if ($mediaId > 0) {
            return ($this->mediaResolver)($mediaId, $src);
        }

        if ($src !== null && ($src === '' || ! $this->isLocalUrl($src))) {
            return null;
        }

        return $src;
    }

    private function safeHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return $href !== '' ? $href : null;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, 'mailto:')) {
            return $href;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        return null;
    }

    private function isLocalUrl(string $src): bool
    {
        return str_starts_with($src, '/') || str_starts_with($src, 'assets/');
    }

    private function plainText(array $node): string
    {
        $text = '';
        foreach ($node['content'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['type'] ?? '') === 'text') {
                $text .= (string) ($child['text'] ?? '');
            } else {
                $text .= ' '.$this->plainText($child);
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function uniqueHeadingId(string $text): string
    {
        $base = Str::slug($text) ?: 'section';
        $count = $this->headingCounts[$base] ?? 0;
        $this->headingCounts[$base] = $count + 1;

        return $count === 0 ? $base : $base.'-'.($count + 1);
    }

    private function countWords(string $text): void
    {
        $this->wordCount += preg_match_all('/[\p{L}\p{N}]+(?:[\'’-][\p{L}\p{N}]+)*/u', $text);
    }
}
