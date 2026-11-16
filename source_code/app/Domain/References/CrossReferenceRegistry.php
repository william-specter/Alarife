<?php

namespace App\Domain\References;

use App\Domain\Activity\RecordActivity;
use App\Models\Article;
use App\Models\CrossReference;
use App\Models\Encyclopedia;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrossReferenceRegistry
{
    public function __construct(private readonly RecordActivity $activity) {}

    /**
     * Search published articles and topics belonging to other publicly readable encyclopedias.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?int $excludeEncyclopediaId = null, int $limit = 24): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $works = Encyclopedia::query()
            ->where('status', 'published')
            ->whereIn('visibility', ['public', 'unlisted'])
            ->when($excludeEncyclopediaId, fn ($q) => $q->whereKeyNot($excludeEncyclopediaId))
            ->get(['id', 'title', 'slug', 'visibility']);

        if ($works->isEmpty()) {
            return [];
        }

        $workIds = $works->pluck('id');
        $workMap = $works->keyBy('id');
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
        $results = [];

        $articles = Article::query()
            ->with('topic:id,title')
            ->whereIn('encyclopedia_id', $workIds)
            ->published()
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like))
            ->orderBy('title')
            ->limit($limit)
            ->get();

        foreach ($articles as $article) {
            $work = $workMap->get($article->encyclopedia_id);
            if (! $work) {
                continue;
            }
            $results[] = [
                'key' => 'article:'.$article->id,
                'target_type' => 'article',
                'target_id' => $article->id,
                'target_encyclopedia_id' => $work->id,
                'title' => $article->title,
                'encyclopedia_title' => $work->title,
                'encyclopedia_slug' => $work->slug,
                'excerpt' => $article->excerpt,
                'topic' => $article->topic?->title,
                'url' => route('reader.article', [$work->slug, $article->slug]),
            ];
        }

        $topics = Topic::query()
            ->whereIn('encyclopedia_id', $workIds)
            ->where('status', 'published')
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
            ->orderBy('title')
            ->limit($limit)
            ->get();

        foreach ($topics as $topic) {
            $work = $workMap->get($topic->encyclopedia_id);
            if (! $work) {
                continue;
            }
            $results[] = [
                'key' => 'topic:'.$topic->id,
                'target_type' => 'topic',
                'target_id' => $topic->id,
                'target_encyclopedia_id' => $work->id,
                'title' => $topic->title,
                'encyclopedia_title' => $work->title,
                'encyclopedia_slug' => $work->slug,
                'excerpt' => $topic->description,
                'topic' => null,
                'url' => route('reader.topic', [$work->slug, $topic->slug]),
            ];
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Replace the source encyclopedia's cross-references after validating every target.
     *
     * @param  array<int, array<string, mixed>>  $references
     *
     * @throws ValidationException
     */
    public function persist(Encyclopedia $source, array $references, ?User $user = null): void
    {
        $validated = [];

        foreach (array_values($references) as $index => $reference) {
            $type = $reference['target_type'] ?? null;
            $targetId = (int) ($reference['target_id'] ?? 0);
            $workId = (int) ($reference['target_encyclopedia_id'] ?? 0);

            if (! in_array($type, ['article', 'topic'], true) || $targetId <= 0 || $workId <= 0) {
                throw ValidationException::withMessages(['references' => 'One of the selected references is invalid.']);
            }

            if ($workId === $source->id) {
                throw ValidationException::withMessages(['references' => 'An encyclopedia cannot reference its own content.']);
            }

            $targetWork = Encyclopedia::find($workId);
            if (! $targetWork || $targetWork->status !== 'published' || ! in_array($targetWork->visibility, ['public', 'unlisted'], true)) {
                throw ValidationException::withMessages(['references' => 'References must point to published, readable encyclopedias.']);
            }

            if ($type === 'article') {
                $target = $targetWork->articles()->whereKey($targetId)->where('status', 'published')->first();
            } else {
                $target = $targetWork->topics()->whereKey($targetId)->where('status', 'published')->first();
            }

            if (! $target) {
                throw ValidationException::withMessages(['references' => 'Cross-references must point to published articles or topics.']);
            }

            $key = $type.':'.$targetId;
            if (isset($validated[$key])) {
                continue;
            }

            $validated[$key] = [
                'source_encyclopedia_id' => $source->id,
                'target_encyclopedia_id' => $workId,
                'target_type' => $type,
                'target_id' => $targetId,
                'note' => isset($reference['note']) ? mb_substr((string) $reference['note'], 0, 300) : null,
                'sort_order' => $index,
            ];
        }

        DB::transaction(function () use ($source, $validated, $user) {
            $source->crossReferences()->delete();
            if ($validated !== []) {
                CrossReference::insert(array_values($validated));
            }
            ($this->activity)($source, $user, 'references.updated', $source, ['count' => count($validated)]);
        });
    }

    /**
     * References for the authoring UI, including stale targets.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forEditor(Encyclopedia $source): array
    {
        $references = $source->crossReferences()
            ->with('targetEncyclopedia:id,title,slug,status,visibility')
            ->get();

        return $references->map(function (CrossReference $reference) {
            $target = $reference->target();
            $available = $target !== null
                && $reference->targetEncyclopedia?->status === 'published'
                && in_array($reference->targetEncyclopedia->visibility, ['public', 'unlisted'], true)
                && ($reference->target_type === 'article'
                    ? ($target instanceof Article && $target->isPublished())
                    : ($target instanceof Topic && $target->status === 'published'));

            return [
                'id' => $reference->id,
                'key' => $reference->target_type.':'.$reference->target_id,
                'target_type' => $reference->target_type,
                'target_id' => $reference->target_id,
                'target_encyclopedia_id' => $reference->target_encyclopedia_id,
                'title' => $target?->title ?? 'Unavailable reference',
                'encyclopedia_title' => $reference->targetEncyclopedia?->title,
                'encyclopedia_slug' => $reference->targetEncyclopedia?->slug,
                'excerpt' => $reference->target_type === 'article' ? ($target?->excerpt) : ($target?->description),
                'note' => $reference->note,
                'available' => $available,
                'url' => $available && $target
                    ? ($reference->target_type === 'article'
                        ? route('reader.article', [$reference->targetEncyclopedia->slug, $target->slug])
                        : route('reader.topic', [$reference->targetEncyclopedia->slug, $target->slug]))
                    : null,
            ];
        })->values()->all();
    }

    /**
     * Pinned references used by readers and publication snapshots.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forReader(Encyclopedia $source): array
    {
        return array_values(array_filter(
            $this->forEditor($source),
            fn (array $reference) => $reference['available'] === true
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function snapshot(Encyclopedia $source): array
    {
        return $this->forReader($source);
    }
}
