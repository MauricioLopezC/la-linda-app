<?php

namespace App\Actions\Catalog;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;

class DiscontinueArticle
{
    public function handle(Article $article): Article
    {
        $article->update([
            'status' => ArticleStatus::Discontinued,
        ]);

        return $article;
    }
}
