<?php

namespace App\Actions\Catalog;

use App\Enums\Catalog\ArticleStatus;
use App\Models\Catalog\Article;

class UpdateArticle
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Article $article, array $data): Article
    {
        $article->update([
            'description' => (string) $data['description'],
            'internal_code' => (string) $data['internal_code'],
            'barcode' => empty($data['barcode']) ? null : (string) $data['barcode'],
            'category_id' => (int) $data['category_id'],
            'brand_id' => empty($data['brand_id']) ? null : (int) $data['brand_id'],
            'unit_of_measure_id' => (int) $data['unit_of_measure_id'],
            'status' => isset($data['status']) ? ArticleStatus::from($data['status']) : $article->status,
            'is_online_publishable' => isset($data['is_online_publishable']) ? (bool) $data['is_online_publishable'] : false,
        ]);

        return $article;
    }
}
