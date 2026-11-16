<?php

namespace Tests\Feature;

use App\Domain\Themes\ThemeRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ReaderSearchAndThemeTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_search_finds_published_articles_and_hides_drafts(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['status' => 'published', 'visibility' => 'public']);
        $topic = $this->makeTopic($encyclopedia);
        $this->makeArticle($encyclopedia, $topic, [
            'title' => 'Giza Pyramids Revealed',
            'slug' => 'giza-pyramids',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->makeArticle($encyclopedia, $topic, [
            'title' => 'Secret Cache of Giza',
            'slug' => 'secret-cache',
            'status' => 'draft',
        ]);

        $response = $this->get("/{$encyclopedia->slug}/search?q=giza")->assertOk();
        $response->assertSee('Giza Pyramids Revealed');
        $response->assertDontSee('Secret Cache of Giza');
    }

    public function test_theme_settings_are_validated_and_do_not_touch_content(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner);
        $article = $this->makeArticle($encyclopedia, $this->makeTopic($encyclopedia));
        $before = $article->fresh()->content;

        $this->actingAs($owner)
            ->put("/author/{$encyclopedia->slug}/appearance", [
                'theme_key' => 'editorial',
                'cover_style' => 'hero',
                'settings' => [
                    'colors' => ['primary' => 'not-a-colour', 'accent' => '#ffffff', 'background' => '#ffffff', 'text' => '#111111'],
                ],
            ])
            ->assertSessionHasErrors('settings.colors.primary');

        $this->assertSame('classic', $encyclopedia->fresh()->theme_key);
        $this->assertSame($before, $article->fresh()->content);

        $theme = app(ThemeRegistry::class)->findOrFail('editorial');
        $this->actingAs($owner)
            ->put("/author/{$encyclopedia->slug}/appearance", [
                'theme_key' => 'editorial',
                'cover_style' => 'hero',
                'settings' => $theme->defaultSettings(),
            ])
            ->assertRedirect();

        $this->assertSame('editorial', $encyclopedia->fresh()->theme_key);
        $this->assertSame($before, $article->fresh()->content);
    }

    public function test_reader_can_download_the_current_release_when_enabled(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['status' => 'published', 'visibility' => 'public']);
        $publication = $encyclopedia->publications()->create([
            'version' => 1,
            'snapshot_path' => 'publications/1/v1.json',
            'theme_key' => 'classic',
            'theme_version' => '1.0.0',
            'theme_settings' => [],
            'topic_count' => 1,
            'article_count' => 1,
            'published_by' => $owner->id,
            'published_at' => now(),
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('exports/1/fake.zip', 'zip-bytes');

        $export = $encyclopedia->exports()->create([
            'publication_id' => $publication->id,
            'template_key' => 'classic',
            'template_version' => '1.0.0',
            'renderer_version' => '1.0.0',
            'options' => [],
            'status' => 'completed',
            'progress' => 100,
            'archive_path' => 'exports/1/fake.zip',
            'cache_key' => 'public:reader',
            'completed_at' => now(),
        ]);

        $this->get("/{$encyclopedia->slug}/download")
            ->assertOk()
            ->assertHeader('content-disposition');

        $encyclopedia->update(['export_enabled' => false]);
        $this->get("/{$encyclopedia->slug}/download")->assertNotFound();
    }
}
