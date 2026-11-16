<?php

namespace App\Http\Controllers\Author;

use App\Domain\Media\StoreMedia;
use App\Domain\Themes\ThemeRegistry;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request, Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);

        $search = trim((string) $request->query('q', ''));
        $media = $encyclopedia->media()
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('filename', 'like', '%'.$search.'%')
                ->orWhere('alt_text', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MediaAsset $asset) => [
                'id' => $asset->id,
                'url' => $asset->url(),
                'filename' => $asset->filename,
                'alt_text' => $asset->alt_text,
                'caption' => $asset->caption,
                'mime_type' => $asset->mime_type,
                'size' => $asset->size,
                'width' => $asset->width,
                'height' => $asset->height,
                'created_at' => $asset->created_at?->toIso8601String(),
            ]);

        return Inertia::render('media/Index', [
            'encyclopedia' => [
                'id' => $encyclopedia->id,
                'title' => $encyclopedia->title,
                'slug' => $encyclopedia->slug,
                'status' => $encyclopedia->status,
                'theme_name' => app(ThemeRegistry::class)->find($encyclopedia->theme_key)?->name,
            ],
            'media' => $media,
            'filters' => ['q' => $search],
            'canWrite' => auth()->user()->can('write', $encyclopedia),
        ]);
    }

    public function store(Request $request, Encyclopedia $encyclopedia, StoreMedia $storeMedia): RedirectResponse|JsonResponse
    {
        $this->authorize('write', $encyclopedia);

        $request->validate([
            'file' => ['required', 'image', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $media = $storeMedia($encyclopedia, $request->user(), $request->file('file'), [
            'alt_text' => $request->input('alt_text'),
            'caption' => $request->input('caption'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'media' => [
                    'id' => $media->id,
                    'url' => $media->url(),
                    'filename' => $media->filename,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'width' => $media->width,
                    'height' => $media->height,
                ],
            ], 201);
        }

        return back()->with('success', 'Image uploaded.');
    }

    public function update(Request $request, Encyclopedia $encyclopedia, MediaAsset $media): RedirectResponse
    {
        $this->authorize('update', $media);
        abort_unless($media->encyclopedia_id === $encyclopedia->id, 404);

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $media->update($data);

        return back()->with('success', 'Image details updated.');
    }

    public function destroy(Request $request, Encyclopedia $encyclopedia, MediaAsset $media, StoreMedia $storeMedia): RedirectResponse
    {
        $this->authorize('delete', $media);
        abort_unless($media->encyclopedia_id === $encyclopedia->id, 404);

        $storeMedia->delete($media, $request->user());

        return back()->with('success', 'Image deleted.');
    }
}
