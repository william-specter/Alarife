<?php

namespace App\Domain\Content;

use Illuminate\Validation\ValidationException;

class ContentValidator
{
    public const ALLOWED_NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem',
        'blockquote', 'codeBlock', 'horizontalRule', 'hardBreak', 'image', 'gallery',
        'table', 'tableRow', 'tableHeader', 'tableCell', 'callout', 'citation', 'timeline',
        'taskList', 'taskItem',
    ];

    /**
     * @throws ValidationException
     */
    public function validate(mixed $doc): array
    {
        if (! is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
            throw ValidationException::withMessages(['content' => 'The article content must be a document.']);
        }

        $encoded = json_encode($doc);
        if ($encoded === false || strlen($encoded) > ContentHtmlRenderer::MAX_BYTES) {
            throw ValidationException::withMessages(['content' => 'The article content is too large (max 2 MB).']);
        }

        $errors = [];
        $images = 0;
        $this->walk($doc, 0, $errors, $images);

        if ($images > ContentHtmlRenderer::MAX_IMAGES) {
            $errors[] = 'An article cannot contain more than 500 images.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['content' => array_slice($errors, 0, 8)]);
        }

        return $doc;
    }

    private function walk(array $node, int $depth, array &$errors, int &$images): void
    {
        if ($depth > ContentHtmlRenderer::MAX_DEPTH) {
            $errors[] = 'The content is nested too deeply.';

            return;
        }

        $type = $node['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::ALLOWED_NODES, true)) {
            $errors[] = 'Unsupported content block: '.($type ?? 'unknown').'.';

            return;
        }

        if ($type === 'image') {
            $images++;
        }
        if ($type === 'gallery') {
            $images += count($node['attrs']['images'] ?? []);
        }
        if ($type === 'heading') {
            $level = (int) ($node['attrs']['level'] ?? 2);
            if ($level < 1 || $level > 4) {
                $errors[] = 'Heading levels must be between 1 and 4.';
            }
        }
        if ($type === 'text' && strlen((string) ($node['text'] ?? '')) > 100_000) {
            $errors[] = 'A text block exceeds the maximum length.';
        }

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $this->walk($child, $depth + 1, $errors, $images);
            }
        }
    }
}
