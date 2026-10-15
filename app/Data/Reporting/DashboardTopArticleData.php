<?php

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

class DashboardTopArticleData extends Data
{
    public function __construct(
        public int $article_id,
        public string $description,
        public string $quantity,
        public string $total_invoiced,
    ) {}
}
