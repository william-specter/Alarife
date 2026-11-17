<?php

namespace App\Domain\Publishing;

use App\Domain\Activity\RecordActivity;
use App\Domain\Exports\RequestExport;
use App\Domain\Realtime\Realtime;
use App\Models\Encyclopedia;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublishEncyclopedia
{
    public function __construct(
        private readonly SnapshotBuilder $snapshots,
        private readonly RecordActivity $activity,
        private readonly RequestExport $exports,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Encyclopedia $encyclopedia, User $user, ?string $notes = null): Publication
    {
        $publishedCount = $encyclopedia->articles()->where('status', 'published')->count();
        if ($publishedCount === 0) {
            throw ValidationException::withMessages([
                'publish' => 'Publish at least one article before releasing this encyclopedia.',
            ]);
        }

        if ($encyclopedia->covers()->count() === 0) {
            throw ValidationException::withMessages([
                'publish' => 'Add at least one cover image before publishing.',
            ]);
        }

        $publication = DB::transaction(function () use ($encyclopedia, $user, $notes) {
            $locked = Encyclopedia::query()->lockForUpdate()->findOrFail($encyclopedia->id);
            $version = ((int) $locked->publications()->max('version')) + 1;
            $snapshot = $this->snapshots->build($locked, $version, $user, $notes);
            $snapshot['encyclopedia']['theme_settings'] = $snapshot['theme']['settings'];

            $path = "publications/{$locked->id}/v{$version}.json";
            $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw ValidationException::withMessages(['publish' => 'The publication snapshot could not be encoded.']);
            }

            Storage::disk('local')->put($path, $encoded);

            $publication = $locked->publications()->create([
                'version' => $version,
                'snapshot_path' => $path,
                'theme_key' => $snapshot['theme']['key'],
                'theme_version' => $snapshot['theme']['version'],
                'theme_settings' => $snapshot['theme']['settings'],
                'topic_count' => $snapshot['counts']['topics'],
                'article_count' => $snapshot['counts']['articles'],
                'published_by' => $user->id,
                'notes' => $notes,
                'published_at' => now(),
            ]);

            $locked->update([
                'status' => 'published',
                'published_at' => $locked->published_at ?? now(),
                'theme_version' => $snapshot['theme']['version'],
                'theme_settings' => $snapshot['theme']['settings'],
            ]);

            return $publication;
        });

        ($this->activity)($encyclopedia, $user, 'encyclopedia.published', $publication, [
            'version' => $publication->version,
            'articles' => $publication->article_count,
        ]);

        $encyclopedia->refresh();
        ($this->exports)($encyclopedia, null, [], $publication, true);
        app(Realtime::class)->encyclopedia($encyclopedia, 'publication.published', [
            'publication_id' => $publication->id,
            'version' => $publication->version,
            'article_count' => $publication->article_count,
            'published_at' => $publication->published_at?->toIso8601String(),
        ]);

        return $publication;
    }
}
