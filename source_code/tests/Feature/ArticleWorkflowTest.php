<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ArticleWorkflowTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_autosave_persists_content_and_rejects_a_stale_revision(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner);
        $topic = $this->makeTopic($encyclopedia);
        $article = $this->makeArticle($encyclopedia, $topic);
        $doc = $this->doc([$this->paragraph('Fresh words for the reader.')]);

        $this->actingAs($owner)
            ->patchJson("/author/{$encyclopedia->slug}/articles/{$article->id}/autosave", [
                'title' => 'Fresh title',
                'slug' => $article->slug,
                'content' => $doc,
                'revision_number' => $article->revision_number,
            ])
            ->assertOk()
            ->assertJsonPath('revision_number', 2);

        $this->assertSame('Fresh title', $article->fresh()->title);

        $this->actingAs($owner)
            ->patchJson("/author/{$encyclopedia->slug}/articles/{$article->id}/autosave", [
                'title' => 'Stale overwrite',
                'slug' => $article->slug,
                'content' => $doc,
                'revision_number' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('revision_number', 2);

        $this->assertSame('Fresh title', $article->fresh()->title);
    }

    public function test_explicit_autosave_creates_a_revision(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner);
        $topic = $this->makeTopic($encyclopedia);
        $article = $this->makeArticle($encyclopedia, $topic);

        $this->actingAs($owner)
            ->patchJson("/author/{$encyclopedia->slug}/articles/{$article->id}/autosave", [
                'title' => $article->title,
                'slug' => $article->slug,
                'content' => $this->doc([$this->paragraph('Explicit save body.')]),
                'revision_number' => 1,
                'explicit' => true,
            ])
            ->assertOk();

        $this->assertSame(2, $article->fresh()->revision_number);
        $this->assertSame(1, $article->fresh()->revisions()->count());
    }

    public function test_published_articles_are_readable_and_drafts_are_not(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['status' => 'published', 'visibility' => 'public']);
        $topic = $this->makeTopic($encyclopedia);
        $draft = $this->makeArticle($encyclopedia, $topic, ['title' => 'Hidden Draft', 'slug' => 'hidden-draft']);
        $live = $this->makeArticle($encyclopedia, $topic, [
            'title' => 'Visible Article',
            'slug' => 'visible-article',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get("/{$encyclopedia->slug}/articles/{$draft->slug}")->assertNotFound();
        $this->get("/{$encyclopedia->slug}/articles/{$live->slug}")->assertOk()->assertSee('Visible Article');
    }

    public function test_authors_can_submit_for_review_but_cannot_publish(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner);
        $encyclopedia->members()->create(['user_id' => $author->id, 'role' => 'author']);
        $topic = $this->makeTopic($encyclopedia);
        $article = $this->makeArticle($encyclopedia, $topic);

        $this->actingAs($author)
            ->post("/author/{$encyclopedia->slug}/articles/{$article->id}/status", ['status' => 'in_review'])
            ->assertRedirect();

        $this->assertSame('in_review', $article->fresh()->status);

        $this->actingAs($author)
            ->post("/author/{$encyclopedia->slug}/articles/{$article->id}/status", ['status' => 'published'])
            ->assertForbidden();
    }

    public function test_revision_restore_creates_a_new_revision(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner);
        $topic = $this->makeTopic($encyclopedia);
        $article = $this->makeArticle($encyclopedia, $topic);
        $article->revisions()->create([
            'editor_id' => $owner->id,
            'revision_number' => 1,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'word_count' => 10,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)->put("/author/{$encyclopedia->slug}/articles/{$article->id}", [
            'title' => 'Second title',
            'slug' => $article->slug,
            'content' => $this->doc([$this->paragraph('Second body.')]),
            'revision_number' => 1,
        ])->assertRedirect();

        $firstRevision = $article->fresh()->revisions()->reorder()->orderBy('revision_number')->first();

        $this->actingAs($owner)
            ->post("/author/{$encyclopedia->slug}/articles/{$article->id}/revisions/{$firstRevision->id}/restore")
            ->assertRedirect();

        $restored = $article->fresh();
        $this->assertSame('Test Article', $restored->title);
        $this->assertSame(3, $restored->revision_number);
        $this->assertSame(3, $restored->revisions()->count());
    }
}
