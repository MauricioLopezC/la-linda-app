<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Freezes the VAT of every sale line (HU-063): rate, net and VAT are stored when the line is
 * added, so a later change of the article's rate never rewrites a sale. Rebuilt instead of
 * altered because the new columns are NOT NULL with CHECKs, which SQLite cannot add in place.
 * No table references sale_items, so there are no foreign keys to drop.
 *
 * List prices include VAT: net = total / (1 + rate), rounded to cents, and VAT = total − net,
 * so net + VAT always adds up to the line total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('sale_items_revised', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles');
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0)');
            $table->foreignId('vat_rate_id')->constrained('vat_rates')->restrictOnDelete();
            $table->rawColumn('vat_rate', 'decimal(5, 2) check (vat_rate >= 0)');
            $table->rawColumn('net_amount', 'decimal(12, 2) check (net_amount >= 0)');

            /* Tolerance instead of equality: SQLite stores decimals as floating point. */
            $table->rawColumn('vat_amount', 'decimal(12, 2) check (vat_amount >= 0 and abs(net_amount + vat_amount - line_total) < 0.005)');
            $table->timestamps();

            $table->unique(['sale_id', 'article_id']);
        });

        DB::table('sale_items')
            ->join('articles', 'articles.id', '=', 'sale_items.article_id')
            ->join('vat_rates', 'vat_rates.id', '=', 'articles.vat_rate_id')
            ->select(['sale_items.*', 'articles.vat_rate_id', 'vat_rates.percentage'])
            ->orderBy('sale_items.id')
            ->each(function (object $item): void {
                $totalCents = (int) round((float) $item->line_total * 100);
                $rateHundredths = (int) round((float) $item->percentage * 100);
                $netCents = intdiv($totalCents * 10000 * 2 + (10000 + $rateHundredths), 2 * (10000 + $rateHundredths));

                DB::table('sale_items_revised')->insert([
                    'id' => $item->id,
                    'sale_id' => $item->sale_id,
                    'article_id' => $item->article_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'price_list_id' => $item->price_list_id,
                    'line_total' => $item->line_total,
                    'vat_rate_id' => $item->vat_rate_id,
                    'vat_rate' => $item->percentage,
                    'net_amount' => number_format($netCents / 100, 2, '.', ''),
                    'vat_amount' => number_format(($totalCents - $netCents) / 100, 2, '.', ''),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            });

        Schema::drop('sale_items');
        Schema::rename('sale_items_revised', 'sale_items');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('sale_items_legacy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles');
            $table->rawColumn('quantity', 'decimal(12, 3) check (quantity > 0)');
            $table->rawColumn('unit_price', 'decimal(12, 2) check (unit_price > 0)');
            $table->foreignId('price_list_id')->constrained('price_lists')->restrictOnDelete();
            $table->rawColumn('line_total', 'decimal(12, 2) check (line_total > 0)');
            $table->timestamps();

            $table->unique(['sale_id', 'article_id']);
        });

        DB::table('sale_items_legacy')->insertUsing(
            ['id', 'sale_id', 'article_id', 'quantity', 'unit_price', 'price_list_id', 'line_total', 'created_at', 'updated_at'],
            DB::table('sale_items')->select(['id', 'sale_id', 'article_id', 'quantity', 'unit_price', 'price_list_id', 'line_total', 'created_at', 'updated_at'])
        );

        Schema::drop('sale_items');
        Schema::rename('sale_items_legacy', 'sale_items');

        $this->resetPostgreSqlSequence();
        Schema::enableForeignKeyConstraints();
    }

    private function resetPostgreSqlSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            select setval(
                pg_get_serial_sequence('sale_items', 'id'),
                coalesce((select max(id) from sale_items), 1),
                exists(select 1 from sale_items)
            )
        SQL);
    }
};
