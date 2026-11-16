<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class EncyclopediaAccessTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_dashboard_lists_only_authorized_encyclopedias(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->makeEncyclopedia($owner, ['title' => 'Mine']);
        $this->makeEncyclopedia($other, ['title' => 'Theirs']);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard', false)
                ->has('encyclopedias', 1)
                ->where('encyclopedias.0.title', $mine->title));
    }

    public function test_platform_admin_sees_every_encyclopedia(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->makeEncyclopedia($owner);
        $this->makeEncyclopedia($owner, ['slug' => 'second-work']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('encyclopedias', 2));
    }

    public function test_private_encyclopedia_is_hidden_from_readers(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['status' => 'published', 'visibility' => 'private']);

        $this->get('/'.$encyclopedia->slug)->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/'.$encyclopedia->slug)->assertNotFound();
        $this->actingAs($owner)->get('/'.$encyclopedia->slug)->assertNotFound();
    }

    public function test_public_published_encyclopedia_is_readable(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner, ['status' => 'published', 'visibility' => 'public']);
        $this->makeTopic($encyclopedia);

        $this->get('/'.$encyclopedia->slug)->assertOk();
    }

    public function test_author_cannot_cross_tenants_through_nested_ids(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $mine = $this->makeEncyclopedia($owner, ['slug' => 'mine-work']);
        $target = $this->makeEncyclopedia($owner, ['slug' => 'target-work', 'owner_id' => $owner->id]);
        $article = $this->makeArticle($target, $this->makeTopic($target));

        $mine->members()->create(['user_id' => $intruder->id, 'role' => 'editor']);

        $this->actingAs($intruder)
            ->get("/author/mine-work/articles/{$article->id}/edit")
            ->assertForbidden();
    }

    public function test_a_fourth_cover_position_is_rejected_by_the_database(): void
    {
        $owner = User::factory()->create();
        $encyclopedia = $this->makeEncyclopedia($owner);
        $this->makeTopic($encyclopedia);

        foreach ([1, 2, 3] as $position) {
            $encyclopedia->covers()->create([
                'media_asset_id' => $this->makeMedia($encyclopedia, "cover-{$position}.png")->id,
                'position' => $position,
            ]);
        }

        $this->expectException(QueryException::class);

        $encyclopedia->covers()->create([
            'media_asset_id' => $this->makeMedia($encyclopedia, 'cover-4.png')->id,
            'position' => 4,
        ]);
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/encyclopedias', [
                'title' => 'Reserved Path',
                'slug' => 'dashboard',
                'language' => 'en',
                'visibility' => 'private',
                'theme_key' => 'classic',
                'cover_style' => 'hero',
            ])
            ->assertSessionHasErrors('slug');
    }
}
