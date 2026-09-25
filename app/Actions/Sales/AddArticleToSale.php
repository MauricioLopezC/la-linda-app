<?php

namespace App\Actions\Sales;

use App\Actions\Pricing\ResolveArticlePrice;
use App\Enums\Catalog\ArticleStatus;
use App\Exceptions\Pricing\ArticleNotPricedException;
use App\Models\Catalog\Article;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddArticleToSale
{
    public function __construct(private ResolveArticlePrice $resolveArticlePrice) {}

    /**
     * Add an article to an open sale (HU-040), priced by the HU-056 cascade (HU-041).
     *
     * The article comes either by id (search) or by a scanned code, matched exactly against
     * the barcode and then the internal code. Adding an article already in the sale adds to
     * its quantity and keeps the price the line was created with.
     *
     * @param  array{article_id?: int|string|null, code?: string|null, quantity?: int|float|string|null}  $data
     */
    public function handle(Sale $sale, array $data): SaleItem
    {
        $field = empty($data['article_id']) ? 'code' : 'article_id';

        if (! $sale->isOpen()) {
            throw ValidationException::withMessages([
                $field => 'La venta ya no está abierta y no admite cambios.',
            ]);
        }

        $article = $this->findArticle(
            empty($data['article_id']) ? null : (int) $data['article_id'],
            (string) ($data['code'] ?? ''),
            $field,
        );
        $quantity = (float) ($data['quantity'] ?? 1);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        if (! $article->unitOfMeasure->allows_decimal_quantity && floor($quantity) !== $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "El artículo \"{$article->description}\" solo admite cantidades enteras.",
            ]);
        }

        return DB::transaction(function () use ($sale, $article, $quantity, $field): SaleItem {
            /** @var SaleItem|null $item */
            $item = $sale->items()->where('article_id', $article->id)->lockForUpdate()->first();

            if ($item !== null) {
                $newQuantity = number_format((float) $item->quantity + $quantity, 3, '.', '');

                $item->update([
                    'quantity' => $newQuantity,
                    'line_total' => SaleItem::calculateLineTotal($newQuantity, $item->unit_price),
                ]);
            } else {
                $item = $this->createPricedItem($sale, $article, $quantity, $field);
            }

            $sale->recalculateTotal();

            return $item;
        });
    }

    /**
     * Find the article by id, or else by an exact scanned code: barcode first, then internal code.
     */
    private function findArticle(?int $articleId, string $code, string $field): Article
    {
        if ($articleId !== null) {
            $article = Article::find($articleId);
        } else {
            $normalizedCode = Article::normalizeUniqueValue($code);

            $article = Article::query()->where('barcode_normalized', $normalizedCode)->first()
                ?? Article::query()->where('internal_code_normalized', $normalizedCode)->first();
        }

        if ($article === null) {
            throw ValidationException::withMessages([
                $field => 'No se encontró ningún artículo con ese código.',
            ]);
        }

        if ($article->status !== ArticleStatus::Active) {
            throw ValidationException::withMessages([
                $field => "El artículo \"{$article->description}\" no está activo.",
            ]);
        }

        return $article;
    }

    private function createPricedItem(Sale $sale, Article $article, float $quantity, string $field): SaleItem
    {
        try {
            $resolvedPrice = $this->resolveArticlePrice->execute(
                $article,
                $sale->channel->toPriceListChannel(),
                $sale->customer,
            );
        } catch (ArticleNotPricedException) {
            throw ValidationException::withMessages([
                $field => "El artículo \"{$article->description}\" no tiene precio en ninguna lista aplicable.",
            ]);
        }

        $quantityString = number_format($quantity, 3, '.', '');

        return $sale->items()->create([
            'article_id' => $article->id,
            'quantity' => $quantityString,
            'unit_price' => $resolvedPrice->unit_price,
            'price_list_id' => $resolvedPrice->price_list_id,
            'line_total' => SaleItem::calculateLineTotal($quantityString, $resolvedPrice->unit_price),
        ]);
    }
}
