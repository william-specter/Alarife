<?php

namespace App\Domain\Topics;

use App\Models\Encyclopedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderTopics
{
    /**
     * @param  array<int, array{id: int, parent_id: int|null, sort_order: int}>  $items
     */
    public function __invoke(Encyclopedia $encyclopedia, array $items): void
    {
        $topics = $encyclopedia->topics()->get()->keyBy('id');

        DB::transaction(function () use ($topics, $items, $encyclopedia) {
            foreach ($items as $item) {
                $topic = $topics->get((int) ($item['id'] ?? 0));
                if (! $topic) {
                    throw ValidationException::withMessages(['topics' => 'Unknown topic in reorder request.']);
                }

                $parentId = isset($item['parent_id']) && $item['parent_id'] !== null ? (int) $item['parent_id'] : null;
                if ($parentId !== null) {
                    $parent = $topics->get($parentId);
                    if (! $parent || $parent->encyclopedia_id !== $encyclopedia->id || $parent->parent_id !== null) {
                        throw ValidationException::withMessages(['topics' => 'Topics can only be nested one level deep.']);
                    }
                    if ($parentId === $topic->id) {
                        throw ValidationException::withMessages(['topics' => 'A topic cannot be its own parent.']);
                    }
                }

                $topic->update([
                    'parent_id' => $parentId,
                    'sort_order' => (int) ($item['sort_order'] ?? $topic->sort_order),
                ]);
            }
        });
    }
}
