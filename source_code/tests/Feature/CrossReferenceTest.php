<?php

namespace Tests\Feature;

use App\Domain\Publishing\PublishEncyclopedia;
use App\Domain\References\CrossReferenceRegistry;
use App\Models\CrossReference;
use App\Models\Encyclopedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class CrossReferenceTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_creation_accepts_published_articles_and_topics_from_other_encyclopedias(): void
    {
        $owner = User::factory()->create();
        $target = $this->publishedTarget();
        $article = $target->articles()->first();
        $topic = $target->topics()->first();

        $this->actingAs($owner)->post('/encyclopedias', [
            'title' => 'Networked Reference',
            'slug' => 'networked-reference',
            'language' => 'en',
            'visibility' => 'private',
            'theme_key' => 'classic',
            'cover_style' => 'hero',
            'export_enabled' => true,
            'references' => [
                ['target_type' => 'article', 'target_id' => $article->id, 'target_encyclopedia_id' => $target->id, 'note' => 'A linked article.'],
                ['target_type' => 'topic', 'target_id' => $topic->id, 'target_encyclopedia_id' => $target->id],
            ],
        ])->assertRedirect();

        $source = Encyclopedia::where('slug', 'networked-reference')->firstOrFail();
        $this->assertSame(2, $source->crossReferences()->count());
        $this->assertDatabaseHas('cross_references', [
            'source_encyclopedia_id' => $source->id,
            'target_encyclopedia_id' => $target->id,
            'target_type' => 'article',
            'target_id' => $article->id,
        ]);
    }

    public function test_settings_reject_unpublished_targets_and_roll_back(): void
    {
        $owner = User::factory()->create();
        $source = $this->makeEncyclopedia($owner, ['slug' => 'reference-source']);
        $draftTarget = $this->makeEncyclopedia(User::factory()->create(), [
            'slug' => 'draft-target',
            'status' => 'draft',
            'visibility' => 'public',
        ]);
        $draftArticle = $this->makeArticle($draftTarget, $this->makeTopic($draftTarget));

        $this->actingAs($owner)->put("/author/{$source->slug}/settings", [
            'title' => 'Changed title that must roll back',
            'slug' => $source->slug,
            'language' => 'en',
            'visibility' => 'private',
            'cover_style' => 'hero',
            'export_enabled' => true,
            'references' => [
                ['target_type' => 'article', 'target_id' => $draftArticle->id, 'target_encyclopedia_id' => $draftTarget->id],
            ],
        ])->assertSessionHasErrors('references');

        $this->assertSame(0, CrossReference::count());
        $this->assertNotSame('Changed title that must roll back', $source->fresh()->title);
    }

    public function test_reader_and_snapshot_include_cross_references(): void
    {
        Storage::fake('local');
        Queue::fake();

        $owner = User::factory()->create();
        $source = $this->makeEncyclopedia($owner, ['slug' => 'network-source', 'visibility' => 'public']);
        $topic = $this->makeTopic($source);
        $this->makeArticle($source, $topic, [
            'title' => 'Source Article',
            'slug' => 'source-article',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->makeMedia($source);
        $source->covers()->create(['media_asset_id' => $source->media()->first()->id, 'position' => 1]);

        $target = $this->publishedTarget();
        $targetArticle = $target->articles()->first();

        app(CrossReferenceRegistry::class)->persist($source, [
            ['target_type' => 'article', 'target_id' => $targetArticle->id, 'target_encyclopedia_id' => $target->id, 'note' => 'Further reading.'],
        ], $owner);

        $publication = app(PublishEncyclopedia::class)($source, $owner, 'Network release');
        $snapshot = json_decode(Storage::disk('local')->get($publication->snapshot_path), true);

        $this->assertCount(1, $snapshot['cross_references']);
        $this->assertSame('Target Article', $snapshot['cross_references'][0]['title']);

        $this->get("/{$source->slug}")
            ->assertOk()
            ->assertSee('Related works')
            ->assertSee('Target Article')
            ->assertSee('Test Target Encyclopedia');
    }

    public function test_cross_reference_search_returns_only_published_targets(): void
    {
        $user = User::factory()->create();
        $source = $this->makeEncyclopedia($user, ['slug' => 'search-source']);
        $target = $this->publishedTarget();

        $this->actingAs($user)
            ->getJson(route('author.references.search', ['q' => 'Target', 'exclude' => $source->slug]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Target Article'])
            ->assertJsonFragment(['title' => 'Target Topic']);
    }

    private function publishedTarget(): Encyclopedia
    {
        $target = $this->makeEncyclopedia(User::factory()->create(), [
            'title' => 'Test Target Encyclopedia',
            'slug' => 'test-target-encyclopedia',
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now(),
        ]);
        $topic = $this->makeTopic($target, ['title' => 'Target Topic', 'slug' => 'target-topic']);
        $this->makeArticle($target, $topic, [
            'title' => 'Target Article',
            'slug' => 'target-article',
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $target;
    }
}
