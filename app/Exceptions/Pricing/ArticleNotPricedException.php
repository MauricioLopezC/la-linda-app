<?php

namespace App\Exceptions\Pricing;

use App\Models\Catalog\Article;

/**
 * Thrown when no active, currently-valid price list in the resolution cascade
 * contains a price for the requested article.
 *
 * The sale must never proceed at zero or an estimated price, so this exception
 * signals a hard rejection that the caller must surface to the user.
 */
class ArticleNotPricedException extends \RuntimeException
{
    public function __construct(Article $article)
    {
        parent::__construct(
            "El artículo \"{$article->description}\" (#{$article->id}) no tiene precio en ninguna lista aplicable según la cascada de precios."
        );
    }
}
