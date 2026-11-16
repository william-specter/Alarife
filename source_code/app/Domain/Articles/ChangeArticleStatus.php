<?php

namespace App\Domain\Articles;

use App\Domain\Activity\RecordActivity;
use App\Models\Article;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ChangeArticleStatus
{
    public function __construct(
        private readonly SaveArticle $saver,
        private readonly RecordActivity $activity,
    ) {}

    public function __invoke(Article $article, string $status, User $user): Article
    {
        if (! in_array($status, ['draft', 'in_review', 'published', 'archived'], true)) {
            throw ValidationException::withMessages(['status' => 'Unknown article status.']);
        }

        $allow = [
            'draft' => ['draft', 'in_review', 'published', 'archived'],
            'in_review' => ['draft', 'in_review', 'published', 'archived'],
            'published' => ['published', 'draft', 'archived'],
            'archived' => ['archived', 'draft'],
        ];

        if (! in_array($status, $allow[$article->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move an article from {$article->status} to {$status}.",
            ]);
        }

        if ($status === 'published' && blank($article->content['content'] ?? null)) {
            throw ValidationException::withMessages(['status' => 'Add some content before publishing.']);
        }

        $article->status = $status;
        $article->updated_by = $user->id;
        if ($status === 'published') {
            $article->published_at ??= now();
        }
        $article->revision_number = $article->revision_number + 1;
        $article->save();

        $this->saver->writeRevision($article, $user);

        ($this->activity)($article->encyclopedia, $user, 'article.'.$status, $article, [
            'title' => $article->title,
        ]);

        return $article;
    }
}
