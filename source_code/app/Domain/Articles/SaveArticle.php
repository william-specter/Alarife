<?php

namespace App\Domain\Articles;

use App\Domain\Activity\RecordActivity;
use App\Domain\Content\ContentHtmlRenderer;
use App\Domain\Content\ContentValidator;
use App\Domain\Content\Slug;
use App\Models\Article;
use App\Models\ArticleRedirect;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SaveArticle
{
    public function __construct(
        private readonly ContentValidator $validator,
        private readonly ContentHtmlRenderer $renderer,
        private readonly RecordActivity $activity,
    ) {}

    /**
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function __invoke(Article $article, User $user, array $data, bool $explicit = true): Article
    {
        $expected = isset($data['revision_number']) ? (int) $data['revision_number'] : null;
        if ($expected !== null && $expected !== $article->revision_number) {
            throw new ConflictHttpException(
                'This article was updated elsewhere (server revision '.$article->revision_number.'). Reload before saving.'
            );
        }

        $content = $this->validator->validate($data['content'] ?? $article->content ?? ['type' => 'doc', 'content' => []]);

        if (! empty($data['topic_id'])) {
            $belongs = $article->encyclopedia->topics()->whereKey($data['topic_id'])->exists();
            if (! $belongs) {
                throw ValidationException::withMessages(['topic_id' => 'Choose a topic from this encyclopedia.']);
            }
        }

        if (! empty($data['featured_image_id'])) {
            $belongs = $article->encyclopedia->media()->whereKey($data['featured_image_id'])->exists();
            if (! $belongs) {
                throw ValidationException::withMessages(['featured_image_id' => 'Choose an image from this encyclopedia.']);
            }
        }

        return DB::transaction(function () use ($article, $user, $data, $content, $explicit) {
            $oldSlug = $article->slug;
            $newSlug = isset($data['slug']) && trim((string) $data['slug']) !== ''
                ? Slug::uniqueFor(Article::class, $article->encyclopedia_id, $data['slug'], $article->id)
                : $article->slug;

            $title = $data['title'] ?? $article->title;
            $excerpt = array_key_exists('excerpt', $data) ? $data['excerpt'] : $article->excerpt;

            $article->fill([
                'title' => $title,
                'slug' => $newSlug,
                'excerpt' => $excerpt,
                'content' => $content,
                'topic_id' => $data['topic_id'] ?? $article->topic_id,
                'featured_image_id' => $data['featured_image_id'] ?? $article->featured_image_id,
                'updated_by' => $user->id,
            ]);
            $article->revision_number = $article->revision_number + 1;
            $article->save();

            if ($oldSlug !== $newSlug && $article->status === 'published') {
                ArticleRedirect::updateOrCreate(
                    ['encyclopedia_id' => $article->encyclopedia_id, 'from_slug' => $oldSlug],
                    ['to_article_id' => $article->id]
                );
            }

            if ($explicit) {
                $this->writeRevision($article, $user);
                ($this->activity)($article->encyclopedia, $user, 'article.saved', $article, [
                    'title' => $article->title,
                    'revision' => $article->revision_number,
                ]);
            }

            return $article;
        });
    }

    public function writeRevision(Article $article, ?User $user): ArticleRevision
    {
        $words = $this->renderer->wordCount($article->content ?? []);

        return ArticleRevision::updateOrCreate(
            ['article_id' => $article->id, 'revision_number' => $article->revision_number],
            [
                'editor_id' => $user?->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'word_count' => $words,
                'created_at' => now(),
            ]
        );
    }
}
