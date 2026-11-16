<?php

namespace App\Http\Controllers\Author;

use App\Domain\Activity\RecordActivity;
use App\Domain\Exports\RequestExport;
use App\Domain\Themes\ThemeRegistry;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use App\Models\EncyclopediaExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExportController extends Controller
{
    public function index(Encyclopedia $encyclopedia, RequestExport $requestExport): Response
    {
        $this->authorize('view', $encyclopedia);
        $encyclopedia->load(['publications']);

        $latest = $encyclopedia->publications->sortByDesc('version')->first();

        return Inertia::render('exports/Index', [
            'encyclopedia' => [
                'id' => $encyclopedia->id,
                'title' => $encyclopedia->title,
                'slug' => $encyclopedia->slug,
                'export_enabled' => $encyclopedia->export_enabled,
                'status' => $encyclopedia->status,
                'theme_name' => app(ThemeRegistry::class)->find($encyclopedia->theme_key)?->name,
            ],
            'exports' => $encyclopedia->exports()->with(['publication', 'requester'])->latest()->limit(30)->get()->map(fn (EncyclopediaExport $export) => [
                'id' => $export->id,
                'status' => $export->status,
                'progress' => $export->progress,
                'source' => $export->sourceLabel(),
                'publication_id' => $export->publication_id,
                'options' => $export->options,
                'archive_size' => $export->archive_size,
                'checksum' => $export->checksum,
                'error_message' => $export->error_message,
                'requested_by' => $export->requester?->name,
                'created_at' => $export->created_at?->toIso8601String(),
                'completed_at' => $export->completed_at?->toIso8601String(),
                'download_url' => $export->isCompleted() ? route('author.exports.download', [$encyclopedia, $export]) : null,
            ]),
            'latestRelease' => $latest ? [
                'id' => $latest->id,
                'version' => $latest->version,
                'article_count' => $latest->article_count,
                'topic_count' => $latest->topic_count,
                'published_at' => $latest->published_at?->toIso8601String(),
                'archive' => $requestExport->publicArchive($encyclopedia)?->only(['id', 'archive_size', 'checksum', 'completed_at']),
            ] : null,
            'counts' => [
                'articles' => $encyclopedia->articles()->count(),
                'published' => $encyclopedia->articles()->where('status', 'published')->count(),
                'topics' => $encyclopedia->topics()->count(),
                'media' => $encyclopedia->media()->count(),
            ],
            'canExport' => auth()->user()->can('export', $encyclopedia),
            'canPublish' => auth()->user()->can('publish', $encyclopedia),
        ]);
    }

    public function store(Request $request, Encyclopedia $encyclopedia, RequestExport $requestExport): RedirectResponse
    {
        $this->authorize('export', $encyclopedia);

        $data = $request->validate([
            'source' => ['required', 'in:release,draft'],
            'include_search' => ['boolean'],
            'include_javascript' => ['boolean'],
            'include_media' => ['boolean'],
            'include_sitemap' => ['boolean'],
        ]);

        $publication = $data['source'] === 'release' ? $encyclopedia->latestPublication() : null;
        if ($data['source'] === 'release' && ! $publication) {
            return back()->with('error', 'Publish a release before exporting the published edition.');
        }

        $export = $requestExport($encyclopedia, $request->user(), [
            'include_search' => $data['include_search'] ?? true,
            'include_javascript' => $data['include_javascript'] ?? true,
            'include_media' => $data['include_media'] ?? true,
            'include_sitemap' => $data['include_sitemap'] ?? true,
        ], $publication);

        app(RecordActivity::class)($encyclopedia, $request->user(), 'export.requested', $export, [
            'source' => $export->sourceLabel(),
        ]);

        return back()->with('success', $export->status === 'completed'
            ? 'Archive is ready to download.'
            : 'Export queued. The archive will be ready shortly.');
    }

    public function status(Encyclopedia $encyclopedia, EncyclopediaExport $export): JsonResponse
    {
        $this->authorize('view', $export);
        abort_unless($export->encyclopedia_id === $encyclopedia->id, 404);

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'progress' => $export->progress,
            'error_message' => $export->error_message,
            'archive_size' => $export->archive_size,
            'checksum' => $export->checksum,
            'download_url' => $export->isCompleted() ? route('author.exports.download', [$encyclopedia, $export]) : null,
        ]);
    }

    public function download(Request $request, Encyclopedia $encyclopedia, EncyclopediaExport $export)
    {
        $this->authorize('download', $export);
        abort_unless($export->encyclopedia_id === $encyclopedia->id, 404);
        abort_unless($export->isCompleted(), 404, 'This archive is not ready.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($export->archive_path), 404, 'The archive file is missing. Re-run the export.');

        app(RecordActivity::class)($encyclopedia, $request->user(), 'export.downloaded', $export);

        return $disk->download($export->archive_path, $export->downloadName());
    }
}
