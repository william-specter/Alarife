<?php

namespace App\Http\Controllers;

use App\Models\Encyclopedia;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $encyclopedias = Encyclopedia::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->with(['covers.media'])
            ->withCount(['topics', 'articles' => fn ($q) => $q->where('status', 'published')])
            ->orderByDesc('published_at')
            ->limit(12)
            ->get()
            ->map(fn (Encyclopedia $encyclopedia) => [
                'title' => $encyclopedia->title,
                'subtitle' => $encyclopedia->subtitle,
                'description' => $encyclopedia->description,
                'slug' => $encyclopedia->slug,
                'cover_url' => $encyclopedia->covers->first()?->media?->url(),
                'topics_count' => $encyclopedia->topics_count,
                'articles_count' => $encyclopedia->articles_count,
                'theme_key' => $encyclopedia->theme_key,
            ]);

        return Inertia::render('Welcome', [
            'encyclopedias' => $encyclopedias,
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
    }
}
