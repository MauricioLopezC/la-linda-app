<?php

namespace App\Actions\Catalog;

use App\Models\Catalog\ArticleSupplier;
use Illuminate\Support\Facades\DB;

class DetachSupplierFromArticle
{
    /**
     * Remove the association between an article and a supplier.
     */
    public function handle(ArticleSupplier $association): void
    {
        DB::transaction(function () use ($association): void {
            $association->delete();
        });
    }
}
