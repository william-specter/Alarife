<?php

namespace App\Domain\Articles;

use App\Domain\Activity\RecordActivity;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreRevision
{
    public function __construct(
        private readonly SaveArticle $saver,
        private readonly RecordActivity $activity,
    ) {}

    public function __invoke(Article $article, ArticleRevision $revision, User $user): Article
    {
        return DB::transaction(function () use ($article, $revision, $user) {
            $article->fill([
                'title' => $revision->title,
                'excerpt' => $revision->excerpt,
                'content' => $revision->content,
            ]);
            $article->revision_number = $article->revision_number + 1;
            $article->updated_by = $user->id;
            $article->save();

            $this->saver->writeRevision($article, $user);

            ($this->activity)($article->encyclopedia, $user, 'article.restored', $article, [
                'restored_from' => $revision->revision_number,
                'revision' => $article->revision_number,
            ]);

            return $article;
        });
    }
}
