<?php

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Author\Concerns\PresentsEncyclopedia;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Encyclopedia;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use PresentsEncyclopedia;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $encyclopedias = $user->accessibleEncyclopedias()
            ->with(['covers.media', 'publications'])
            ->withCount(['articles as published_articles_count' => fn ($q) => $q->where('status', 'published')])
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->get();

        $activity = ActivityLog::query()
            ->with(['user', 'encyclopedia'])
            ->whereIn('encyclopedia_id', $encyclopedias->pluck('id'))
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => $this->activityRow($log));

        $stats = [
            'encyclopedias' => $encyclopedias->count(),
            'articles' => $encyclopedias->sum('articles_count'),
            'published' => $encyclopedias->sum('published_articles_count'),
            'drafts' => $encyclopedias->sum(fn (Encyclopedia $e) => max(0, ($e->articles_count ?? 0) - ($e->published_articles_count ?? 0))),
        ];

        return Inertia::render('Dashboard', [
            'encyclopedias' => $encyclopedias->map(fn (Encyclopedia $e) => $this->encyclopediaCard($e))->values(),
            'activity' => $activity,
            'stats' => $stats,
            'filters' => ['q' => $search],
        ]);
    }
}
