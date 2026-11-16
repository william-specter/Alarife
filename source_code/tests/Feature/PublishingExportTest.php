<?php

namespace Tests\Feature;

use App\Domain\Exports\GenerateEncyclopediaExport;
use App\Domain\Exports\RequestExport;
use App\Models\EncyclopediaExport;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class PublishingExportTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_publishing_creates_an_immutable_snapshot_and_versions_releases(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$owner, $encyclopedia] = $this->publishableEncyclopedia();

        $this->actingAs($owner)
            ->post("/author/{$encyclopedia->slug}/publishing", ['notes' => 'First edition'])
            ->assertRedirect();

        $encyclopedia->refresh();
        $this->assertSame('published', $encyclopedia->status);

        $first = Publication::where('encyclopedia_id', $encyclopedia->id)->firstOrFail();
        $this->assertSame(1, $first->version);
        $this->assertSame(1, $first->article_count);
        Storage::disk('local')->assertExists($first->snapshot_path);

        $snapshot = json_decode(Storage::disk('local')->get($first->snapshot_path), true);
        $this->assertSame(1, $snapshot['release']['version']);
        $this->assertCount(1, $snapshot['articles']);

        // Editing after release must not mutate the stored snapshot.
        $before = Storage::disk('local')->get($first->snapshot_path);
        $this->actingAs($owner)->post("/author/{$encyclopedia->slug}/publishing", ['notes' => 'Second edition'])->assertRedirect();
        $this->assertSame($before, Storage::disk('local')->get($first->snapshot_path));

        $this->assertSame(2, Publication::where('encyclopedia_id', $encyclopedia->id)->max('version'));
    }

    public function test_export_job_produces_a_complete_offline_archive(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        [$owner, $encyclopedia] = $this->publishableEncyclopedia();

        $this->actingAs($owner)->post("/author/{$encyclopedia->slug}/publishing", ['notes' => 'Release']);
        $publication = Publication::where('encyclopedia_id', $encyclopedia->id)->firstOrFail();

        $export = EncyclopediaExport::where('publication_id', $publication->id)->firstOrFail();
        $this->assertSame('queued', $export->status);

        $job = new GenerateEncyclopediaExport($export->id);
        app()->call([$job, 'handle']);

        $export->refresh();
        $this->assertSame('completed', $export->status, $export->error_message ?? '');
        $this->assertNotNull($export->archive_path);
        $this->assertNotNull($export->checksum);
        Storage::disk('local')->assertExists($export->archive_path);

        $zipPath = Storage::disk('local')->path($export->archive_path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $root = $encyclopedia->slug;
        $this->assertContains($root.'/index.html', $names);
        $this->assertContains($root.'/search/index.html', $names);
        $this->assertContains($root.'/assets/css/style.css', $names);
        $this->assertContains($root.'/assets/js/app.js', $names);
        $this->assertContains($root.'/assets/search/search-index.js', $names);
        $this->assertContains($root.'/articles/'.$this->articleSlug.'/index.html', $names);
        $this->assertContains($root.'/topics/'.$this->topicSlug.'/index.html', $names);

        $html = $zip->getFromName($root.'/index.html');
        $this->assertIsString($html);
        $this->assertStringNotContainsString('http://localhost:8000', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);

        $search = $zip->getFromName($root.'/assets/search/search-index.js');
        $this->assertStringContainsString('ALARIFE_SEARCH_INDEX', $search);
        $this->assertStringContainsString('Test Article', $search);

        $zip->close();
    }

    public function test_draft_export_includes_unpublished_articles(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();

        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['slug' => 'draft-export']);
        $topic = $this->makeTopic($encyclopedia, ['title' => 'Draft Topic', 'slug' => 'draft-topic']);
        $this->makeArticle($encyclopedia, $topic, [
            'title' => 'Unpublished Work',
            'slug' => 'unpublished-work',
            'status' => 'draft',
        ]);
        $this->makeMedia($encyclopedia);
        $cover = $encyclopedia->media()->first();
        $encyclopedia->covers()->create(['media_asset_id' => $cover->id, 'position' => 1]);

        $this->actingAs($owner);
        $export = app(RequestExport::class)($encyclopedia, $owner, [], null, false);
        $this->assertNull($export->publication_id);

        $job = new GenerateEncyclopediaExport($export->id);
        app()->call([$job, 'handle']);

        $export->refresh();
        $this->assertSame('completed', $export->status, $export->error_message ?? '');

        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($export->archive_path));
        $this->assertContains('draft-export/articles/unpublished-work/index.html', array_map(
            fn ($i) => $zip->getNameIndex($i),
            range(0, $zip->numFiles - 1)
        ));
        $zip->close();
    }

    public function test_private_reader_cannot_download_archives(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$owner, $encyclopedia] = $this->publishableEncyclopedia();
        $encyclopedia->update(['visibility' => 'private', 'status' => 'published', 'published_at' => now()]);

        $this->get("/{$encyclopedia->slug}/download")->assertNotFound();
    }

    private string $articleSlug;

    private string $topicSlug;

    private function publishableEncyclopedia(): array
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['slug' => 'export-test-'.substr(uniqid(), -5)]);
        $topic = $this->makeTopic($encyclopedia, ['title' => 'Exported Topic', 'slug' => 'exported-topic']);
        $article = $this->makeArticle($encyclopedia, $topic, [
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->makeMedia($encyclopedia);
        $cover = $encyclopedia->media()->first();
        $encyclopedia->covers()->create(['media_asset_id' => $cover->id, 'position' => 1]);

        $this->articleSlug = $article->slug;
        $this->topicSlug = $topic->slug;

        return [$owner, $encyclopedia];
    }
}
