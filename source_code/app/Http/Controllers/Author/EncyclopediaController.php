<?php

namespace App\Http\Controllers\Author;

use App\Domain\Activity\RecordActivity;
use App\Domain\Content\Slug;
use App\Domain\Encyclopedias\CreateEncyclopedia;
use App\Domain\Encyclopedias\DuplicateEncyclopedia;
use App\Domain\References\CrossReferenceRegistry;
use App\Domain\Themes\ThemeRegistry;
use App\Http\Controllers\Author\Concerns\PresentsEncyclopedia;
use App\Http\Controllers\Controller;
use App\Models\Encyclopedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EncyclopediaController extends Controller
{
    use PresentsEncyclopedia;

    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly CrossReferenceRegistry $references,
    ) {}

    public function create(): Response
    {
        return Inertia::render('encyclopedias/Create', [
            'themes' => $this->themes->all()->map(fn ($theme) => [
                'key' => $theme->key,
                'name' => $theme->name,
                'description' => $theme->description,
                'version' => $theme->version,
                'accent' => $theme->defaults['colors']['accent'],
                'background' => $theme->defaults['colors']['background'],
                'primary' => $theme->defaults['colors']['primary'],
                'preview' => '/theme-previews/'.$theme->previewImage(),
            ])->values(),
            'languages' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'es', 'label' => 'Español'],
                ['code' => 'fr', 'label' => 'Français'],
                ['code' => 'de', 'label' => 'Deutsch'],
                ['code' => 'pt', 'label' => 'Português'],
                ['code' => 'it', 'label' => 'Italiano'],
            ],
        ]);
    }

    public function store(Request $request, CreateEncyclopedia $create): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:240'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:4000'],
            'language' => ['required', 'string', 'max:8'],
            'visibility' => ['required', Rule::in(['private', 'unlisted', 'public'])],
            'theme_key' => ['required', 'string', Rule::in($this->themes->all()->pluck('key')->all())],
            'cover_style' => ['required', Rule::in(['hero', 'editorial', 'cards'])],
            'export_enabled' => ['boolean'],
            'references' => ['nullable', 'array', 'max:50'],
            'references.*.target_type' => ['required', Rule::in(['article', 'topic'])],
            'references.*.target_id' => ['required', 'integer'],
            'references.*.target_encyclopedia_id' => ['required', 'integer'],
            'references.*.note' => ['nullable', 'string', 'max:300'],
        ]);

        Slug::assertReserved($data['slug'] ?? Slug::make($data['title']));
        $references = $data['references'] ?? [];
        unset($data['references']);

        $encyclopedia = DB::transaction(function () use ($create, $request, $data, $references) {
            $encyclopedia = $create($request->user(), $data);
            $this->references->persist($encyclopedia, $references, $request->user());

            return $encyclopedia;
        });

        return redirect()
            ->route('author.overview', $encyclopedia)
            ->with('success', 'Encyclopedia created. Add topics and your first article.');
    }

    public function settings(Encyclopedia $encyclopedia): Response
    {
        $this->authorize('view', $encyclopedia);
        $encyclopedia->load(['covers.media']);

        return Inertia::render('encyclopedias/Settings', [
            'encyclopedia' => $this->encyclopediaCard($encyclopedia),
            'references' => $this->references->forEditor($encyclopedia),
            'role' => $encyclopedia->membershipRole(auth()->user()),
            'canManage' => auth()->user()->can('update', $encyclopedia),
            'languages' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'es', 'label' => 'Español'],
                ['code' => 'fr', 'label' => 'Français'],
                ['code' => 'de', 'label' => 'Deutsch'],
                ['code' => 'pt', 'label' => 'Português'],
                ['code' => 'it', 'label' => 'Italiano'],
            ],
        ]);
    }

    public function update(Request $request, Encyclopedia $encyclopedia): RedirectResponse
    {
        $this->authorize('update', $encyclopedia);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:240'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:4000'],
            'language' => ['required', 'string', 'max:8'],
            'visibility' => ['required', Rule::in(['private', 'unlisted', 'public'])],
            'cover_style' => ['required', Rule::in(['hero', 'editorial', 'cards'])],
            'export_enabled' => ['boolean'],
            'references' => ['nullable', 'array', 'max:50'],
            'references.*.target_type' => ['required', Rule::in(['article', 'topic'])],
            'references.*.target_id' => ['required', 'integer'],
            'references.*.target_encyclopedia_id' => ['required', 'integer'],
            'references.*.note' => ['nullable', 'string', 'max:300'],
        ]);

        $slug = Slug::uniqueFor(Encyclopedia::class, 0, $data['slug'], $encyclopedia->id);
        $references = $data['references'] ?? [];
        unset($data['references']);

        DB::transaction(function () use ($encyclopedia, $data, $slug, $references, $request) {
            $encyclopedia->update([
                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? null,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'language' => $data['language'],
                'visibility' => $data['visibility'],
                'cover_style' => $data['cover_style'],
                'export_enabled' => (bool) ($data['export_enabled'] ?? false),
            ]);

            $this->references->persist($encyclopedia, $references, $request->user());
        });

        app(RecordActivity::class)($encyclopedia, $request->user(), 'encyclopedia.updated', $encyclopedia);

        return back()->with('success', 'Encyclopedia settings saved.');
    }

    public function destroy(Request $request, Encyclopedia $encyclopedia): RedirectResponse
    {
        $this->authorize('delete', $encyclopedia);

        $title = $encyclopedia->title;
        $encyclopedia->delete();

        app(RecordActivity::class)(null, $request->user(), 'encyclopedia.deleted', null, ['title' => $title]);

        return redirect()->route('dashboard')->with('success', 'Encyclopedia deleted.');
    }

    public function duplicate(Request $request, Encyclopedia $encyclopedia, DuplicateEncyclopedia $duplicate): RedirectResponse
    {
        $this->authorize('view', $encyclopedia);

        $copy = $duplicate($encyclopedia, $request->user());

        return redirect()->route('author.overview', $copy)->with('success', 'Encyclopedia duplicated as a private draft.');
    }
}
