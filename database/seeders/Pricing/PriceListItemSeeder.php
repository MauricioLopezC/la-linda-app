<?php

namespace Database\Seeders\Pricing;

use App\Models\Catalog\Article;
use App\Models\Pricing\PriceList;
use App\Models\Pricing\PriceListItem;
use Illuminate\Database\Seeder;

/**
 * Demo prices to show the HU-056 cascade from the counter sale screen:
 *
 * - Lista General: every article except the last one, which stays unpriced to show the
 *   "sin precio" rejection.
 * - Lista Mostrador: every other article, 5 % below General; the rest fall back to General.
 * - Mayorista (particular): every priced article, 15 % below General.
 */
class PriceListItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $general = PriceList::query()->where('name_normalized', 'lista general')->first();
        $mostrador = PriceList::query()->where('name_normalized', 'lista mostrador')->first();
        $mayorista = PriceList::query()->where('name_normalized', 'mayorista')->first();

        if ($general === null || $mostrador === null || $mayorista === null) {
            return;
        }

        $articles = Article::query()->active()->orderBy('internal_code')->get();
        $pricedArticles = $articles->slice(0, max($articles->count() - 1, 0))->values();

        foreach ($pricedArticles as $index => $article) {
            $generalPrice = $this->generalPriceFor($article);

            $this->setPrice($general, $article, $generalPrice);
            $this->setPrice($mayorista, $article, $this->roundToTen($generalPrice * 0.85));

            if ($index % 2 === 0) {
                $this->setPrice($mostrador, $article, $this->roundToTen($generalPrice * 0.95));
            }
        }
    }

    /**
     * A stable, plausible price between $300 and $4,750 derived from the internal code.
     */
    private function generalPriceFor(Article $article): float
    {
        return 300 + (crc32($article->internal_code) % 90) * 50;
    }

    private function roundToTen(float $price): float
    {
        return round($price / 10) * 10;
    }

    private function setPrice(PriceList $priceList, Article $article, float $price): void
    {
        PriceListItem::updateOrCreate(
            ['price_list_id' => $priceList->id, 'article_id' => $article->id],
            ['price' => number_format($price, 2, '.', '')],
        );
    }
}
