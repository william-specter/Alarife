<?php

namespace App\Domain\Exports;

use App\Domain\Themes\ThemeRegistry;
use App\Models\Encyclopedia;
use App\Models\EncyclopediaExport;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RequestExport
{
    public function __construct(private readonly ThemeRegistry $themes) {}

    public function __invoke(
        Encyclopedia $encyclopedia,
        ?User $user,
        array $options = [],
        ?Publication $publication = null,
        bool $public = false,
    ): EncyclopediaExport {
        $options = $this->normalizeOptions($options);
        $theme = $this->themes->findOrFail($encyclopedia->theme_key);

        if ($public) {
            $publication ??= $encyclopedia->latestPublication();
            if (! $publication) {
                throw ValidationException::withMessages([
                    'export' => 'This encyclopedia has no published release to export.',
                ]);
            }
        }

        $source = $publication ? 'public' : 'draft';

        $cacheKey = $this->cacheKey($encyclopedia, $publication, $theme->key, $theme->version, $options, $source);

        $existing = EncyclopediaExport::query()
            ->where('encyclopedia_id', $encyclopedia->id)
            ->where('cache_key', $cacheKey)
            ->where('status', 'completed')
            ->whereNotNull('archive_path')
            ->latest('completed_at')
            ->first();

        if ($existing && $existing->archiveExists()) {
            return $existing;
        }

        $export = EncyclopediaExport::create([
            'encyclopedia_id' => $encyclopedia->id,
            'publication_id' => $publication?->id,
            'requested_by' => $user?->id,
            'template_key' => $theme->key,
            'template_version' => $theme->version,
            'renderer_version' => Renderer::VERSION,
            'options' => $options,
            'status' => 'queued',
            'progress' => 0,
            'cache_key' => $cacheKey,
        ]);

        GenerateEncyclopediaExport::dispatch($export->id)->onQueue(Renderer::QUEUE);

        return $export;
    }

    public function publicArchive(Encyclopedia $encyclopedia): ?EncyclopediaExport
    {
        $publication = $encyclopedia->latestPublication();
        if (! $publication) {
            return null;
        }

        return EncyclopediaExport::query()
            ->where('encyclopedia_id', $encyclopedia->id)
            ->where('publication_id', $publication->id)
            ->where('status', 'completed')
            ->where('cache_key', 'like', 'public:%')
            ->latest('completed_at')
            ->first();
    }

    private function normalizeOptions(array $options): array
    {
        $javascript = (bool) ($options['include_javascript'] ?? true);

        return [
            'include_search' => $javascript && (bool) ($options['include_search'] ?? true),
            'include_javascript' => $javascript,
            'include_media' => (bool) ($options['include_media'] ?? true),
            'include_sitemap' => (bool) ($options['include_sitemap'] ?? true),
        ];
    }

    private function cacheKey(
        Encyclopedia $encyclopedia,
        ?Publication $publication,
        string $themeKey,
        string $themeVersion,
        array $options,
        string $source,
    ): string {
        if ($publication) {
            $identity = 'public:'.$publication->id.':'.$publication->version;
        } else {
            $articles = $encyclopedia->articles()
                ->selectRaw('count(*) as aggregate, coalesce(max(updated_at), now()) as latest, coalesce(max(revision_number), 0) as revision')
                ->first();
            $identity = 'draft:'.$encyclopedia->id.':'.$encyclopedia->updated_at?->timestamp.':'.$articles->aggregate.':'.$articles->revision.':'.strtotime((string) $articles->latest);
        }

        return $source.':'.substr(hash('sha256', $identity.'|'.$themeKey.'|'.$themeVersion.'|'.Renderer::VERSION.'|'.json_encode($options)), 0, 40);
    }

    public static function snapshotPath(EncyclopediaExport $export): ?string
    {
        return $export->publication?->snapshot_path;
    }

    public static function archivePath(EncyclopediaExport $export): string
    {
        return 'exports/'.$export->encyclopedia_id.'/'.$export->id.'.zip';
    }

    public static function snapshotExists(Publication $publication): bool
    {
        return Storage::disk('local')->exists($publication->snapshot_path);
    }
}
