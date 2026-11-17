<?php

namespace App\Domain\Exports;

use App\Domain\Publishing\SnapshotBuilder;
use App\Domain\Realtime\Realtime;
use App\Models\EncyclopediaExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateEncyclopediaExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public int $exportId) {}

    public function handle(
        StaticSiteWriter $writer,
        SnapshotBuilder $snapshots,
        ZipArchiveBuilder $zipper,
    ): void {
        $export = EncyclopediaExport::with(['encyclopedia', 'publication'])->find($this->exportId);
        if (! $export) {
            return;
        }

        $siteDir = storage_path('app/exports/'.$export->id.'/site');

        try {
            $export->update([
                'status' => 'processing',
                'progress' => 10,
                'started_at' => now(),
                'error_message' => null,
            ]);
            $this->broadcast($export, 10);

            $snapshot = $this->snapshot($export, $snapshots);
            $export->update(['progress' => 25]);

            $stats = $writer->write($export, $snapshot, $siteDir);
            $export->update(['progress' => 80]);
            $this->broadcast($export, 80);

            $archivePath = RequestExport::archivePath($export);
            $absolute = Storage::disk('local')->path($archivePath);
            $archive = $zipper->build($siteDir, $export->encyclopedia->slug, $absolute);

            $export->update([
                'status' => 'completed',
                'progress' => 100,
                'archive_path' => $archivePath,
                'archive_size' => $archive['size'],
                'checksum' => $archive['checksum'],
                'completed_at' => now(),
                'error_message' => null,
            ]);
            $this->broadcast($export, 100);

            File::deleteDirectory(storage_path('app/exports/'.$export->id));
        } catch (Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'progress' => 0,
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            $this->broadcast($export, 0);

            File::deleteDirectory(storage_path('app/exports/'.$export->id));

            report($exception);
        }
    }

    private function broadcast(EncyclopediaExport $export, int $progress): void
    {
        $export->refresh();
        app(Realtime::class)->encyclopedia($export->encyclopedia, 'export.updated', [
            'export_id' => $export->id,
            'status' => $export->status,
            'progress' => $progress,
            'archive_size' => $export->archive_size,
            'checksum' => $export->checksum,
            'error_message' => $export->error_message,
            'completed_at' => $export->completed_at?->toIso8601String(),
            'download_url' => $export->isCompleted()
                ? route('author.exports.download', [$export->encyclopedia->slug, $export->id])
                : null,
        ]);
    }

    private function snapshot(EncyclopediaExport $export, SnapshotBuilder $snapshots): array
    {
        if ($export->publication) {
            $publication = $export->publication;
            if (! Storage::disk('local')->exists($publication->snapshot_path)) {
                throw new \RuntimeException('The publication snapshot is missing from storage.');
            }

            $decoded = json_decode((string) Storage::disk('local')->get($publication->snapshot_path), true);
            if (! is_array($decoded)) {
                throw new \RuntimeException('The publication snapshot is not valid JSON.');
            }

            return $decoded;
        }

        return $snapshots->buildDraft($export->encyclopedia);
    }
}
