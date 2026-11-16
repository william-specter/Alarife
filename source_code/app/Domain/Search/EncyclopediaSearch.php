<?php

namespace App\Domain\Search;

use App\Models\Article;
use App\Models\Topic;
use Illuminate\Support\Collection;

class EncyclopediaSearch
{
    /**
     * @return array{articles: Collection<int, Article>, topics: Collection<int, Topic>}
     */
    public function search(int $encyclopediaId, string $query, int $limit = 30): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['articles' => collect(), 'topics' => collect()];
        }

        $driver = Article::query()->getConnection()->getDriverName();

        $articles = Article::query()
            ->with(['topic', 'featuredImage'])
            ->where('encyclopedia_id', $encyclopediaId)
            ->published();

        if ($driver === 'pgsql') {
            $articles->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$query])
                ->orderByRaw("ts_rank(search_vector, plainto_tsquery('simple', ?)) DESC", [$query]);
        } else {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
            $articles->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('content', 'like', $like);
            })->orderByDesc('published_at');
        }

        $topics = Topic::query()
            ->where('encyclopedia_id', $encyclopediaId)
            ->where('status', 'published')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%');
            })
            ->limit(10)
            ->get();

        return ['articles' => $articles->limit($limit)->get(), 'topics' => $topics];
    }
}
